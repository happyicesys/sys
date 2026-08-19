<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Enums\Citybox\DeviceType;
use App\Exceptions\CityboxApiException;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Services\Citybox\DTO\ChillerDevice;
use App\Services\HistoryService;
use App\Services\RunningNumberService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates a Smart Chiller vend from a CityBox device (design §8c).
 *
 * What is automatic: everything their API supplies (identity, type→model,
 * online/heartbeats, their name → the CUSTOMER name), the dedicated Citybox
 * operator, the vend code (running number under the CB prefix), the binding
 * record. What is human: the four things their API cannot supply — which
 * customer (existing by normalised-name match, or a new one), address,
 * contact, contract — captured by the Create page and passed in as $site.
 *
 * Duplicate-proof by three layers: the unique index on
 * vends.citybox_equipment_id, the Form Request rule, and unlinkedDevices()
 * never OFFERING a linked id. Customer never auto-created without a human
 * choosing "create" — and then only once, at this moment.
 */
class DeviceProvisioningService
{
    private const UNLINKED_CACHE_KEY = 'citybox:provisioning:devices';

    public function __construct(
        private ChillerGateway $gateway,
        private DeviceSyncService $deviceSync,
        private HistoryService $history,
        private RunningNumberService $runningNumbers,
    ) {}

    /**
     * Devices in their fleet that are NOT yet linked to a mark1 vend — the
     * Create-page dropdown source. Cached 60 s so ten people opening the page
     * is one API call. Includes a "linked to vend X" annotation for the rest
     * so the UI can explain why an id is missing.
     *
     * @return array{unlinked: Collection<int,ChillerDevice>, linked: array<string,int>}
     */
    public function devices(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::UNLINKED_CACHE_KEY);
        }
        $all = Cache::remember(self::UNLINKED_CACHE_KEY, 60, fn () => $this->gateway->listDevices()->all());
        $linked = Vend::withoutGlobalScopes()->whereNotNull('citybox_equipment_id')
            ->pluck('id', 'citybox_equipment_id')->all();

        return [
            'unlinked' => collect($all)->reject(fn (ChillerDevice $d) => isset($linked[$d->equipmentId]))->values(),
            'linked' => $linked,
        ];
    }

    /** One device by id, or null. Cheap: served from the same 60 s cache. */
    public function device(string $equipmentId): ?ChillerDevice
    {
        return collect(Cache::remember(self::UNLINKED_CACHE_KEY, 60, fn () => $this->gateway->listDevices()->all()))
            ->first(fn (ChillerDevice $d) => $d->equipmentId === $equipmentId);
    }

    /** Preview-card data for a chosen device: live state + product count. Best-effort. */
    public function preview(string $equipmentId): array
    {
        $device = $this->device($equipmentId);
        $state = null;
        $productCount = null;
        try {
            $state = $this->gateway->deviceState($equipmentId)->value;
            $productCount = $this->gateway->restockConfig($equipmentId)->count();
        } catch (\Throwable) {
            // offline devices 400 on stock; the card just shows less
        }

        return [
            'device' => $device,
            'state' => $state,
            'product_count' => $productCount,
            'existing_customer' => $device ? $this->matchCustomerByName($device->name) : null,
        ];
    }

    /**
     * Existing customer whose name equals theirs after normalisation (trim,
     * case-fold, collapse whitespace) under the Citybox operator — so a second
     * chiller at the same site joins that customer instead of "Singapore8 (2)".
     */
    public function matchCustomerByName(string $name): ?Customer
    {
        $norm = self::normaliseName($name);
        if ($norm === '') {
            return null;
        }

        return Customer::withoutGlobalScopes()
            ->where('operator_id', $this->operator()->id)
            ->get(['id', 'name', 'code'])
            ->first(fn (Customer $c) => self::normaliseName((string) $c->name) === $norm);
    }

    public static function normaliseName(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
    }

    /**
     * Create the vend (+ customer if requested) and bind, atomically.
     *
     * @param  array{customer_id?:int|null, new_customer?:array|null, begin_date?:string|null, name?:string|null}  $site
     *                                                                                                                    customer_id  bind to this existing customer, OR
     *                                                                                                                    new_customer ['name','address'=>[...]?, ...] create one (name defaults to their device name)
     */
    public function provision(ChillerDevice $device, array $site, User $by): Vend
    {
        if (Vend::withoutGlobalScopes()->where('citybox_equipment_id', $device->equipmentId)->exists()) {
            throw new CityboxApiException("{$device->equipmentId} is already linked to a vend");
        }

        return DB::transaction(function () use ($device, $site, $by) {
            $operator = $this->operator();
            $prefix = $this->prefix($operator);
            $model = $this->modelFor($device->type);

            $vend = Vend::create([
                'code' => $this->runningNumbers->getRunningCode(new Vend, $operator->id),
                'name' => $site['name'] ?? null,
                'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
                'citybox_equipment_id' => $device->equipmentId,
                'operator_id' => $operator->id,
                'vend_prefix_id' => $prefix->id,
                'vend_model_id' => $model?->id,
                'begin_date' => $site['begin_date'] ?? now()->toDateString(),
                'is_active' => 1,
            ]);
            // Status/online/heartbeats/CityBox name → the same writer the poller uses.
            $this->deviceSync->applyStatus($vend, $device);

            $customer = null;
            if (! empty($site['customer_id'])) {
                $customer = Customer::withoutGlobalScopes()->findOrFail((int) $site['customer_id']);
            } elseif (! empty($site['new_customer'])) {
                $customer = $this->createCustomer($device, $site['new_customer'], $operator);
            }

            if ($customer) {
                $vend->forceFill(['customer_id' => $customer->id, 'binded_at' => now()])->save();
                $this->history->syncVendCustomerMovement($vend, $customer, true);
            }

            Cache::forget(self::UNLINKED_CACHE_KEY);
            Log::info('Citybox vend provisioned', ['vend_id' => $vend->id, 'equipment_id' => $device->equipmentId, 'customer_id' => $customer?->id, 'user_id' => $by->id]);

            return $vend->refresh();
        });
    }

    private function createCustomer(ChillerDevice $device, array $attrs, Operator $operator): Customer
    {
        $name = trim((string) ($attrs['name'] ?? '')) ?: $device->name;
        $customer = Customer::create([
            'name' => $name,
            'code' => $this->runningNumbers->getCustomerRunningCode($operator->id),
            'operator_id' => $operator->id,
            'begin_date' => $attrs['begin_date'] ?? now()->toDateString(),
            'status_id' => Customer::STATUS_ACTIVE,
            'active_date' => now()->toDateString(),
        ] + array_intersect_key($attrs, array_flip(['person_id', 'location_type_id', 'customer_type_id'])));

        if (! empty($attrs['address']) && is_array($attrs['address'])) {
            $customer->deliveryAddress()->updateOrCreate(['type' => Customer::ADDRESS_TYPE_DELIVERY], $attrs['address']);
        }

        return $customer;
    }

    public function operator(): Operator
    {
        return Operator::where('code', config('citybox.operator_code', 'CB'))->firstOr(function () {
            throw new CityboxApiException('Citybox operator not seeded — run CityboxOperatorSeeder');
        });
    }

    private function prefix(Operator $operator): VendPrefix
    {
        return VendPrefix::where('name', config('citybox.vend_prefix_name', 'CB'))->firstOr(function () {
            throw new CityboxApiException('Citybox vend prefix not seeded — run CityboxOperatorSeeder');
        });
    }

    private function modelFor(DeviceType $type): ?VendModel
    {
        $name = config('citybox.device_models')[$type->value] ?? config('citybox.device_models')['unknown'] ?? null;
        if ($type === DeviceType::Unknown) {
            Log::notice('Citybox device type not in citybox.device_models — using generic model', ['type' => $type->value]);
        }

        return $name ? VendModel::where('name', $name)->first() : null;
    }
}
