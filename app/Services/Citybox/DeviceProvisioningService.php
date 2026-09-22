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
use App\Support\VendCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Imports a Smart Chiller from a CityBox device (design §8c).
 *
 * Automatic: everything their API supplies — identity, type→model, online /
 * heartbeats, and the machine ID (their OPS Pro name → code_prefix + code) — plus
 * the dedicated Citybox operator. NOT here: the Site. "Site — Primary: Sys"
 * (Brian, 2026-09-19): a machine arrives with no site, the Site is created in
 * mark1 like any other and bound on Machine Settings. An existing site may be
 * passed as a shortcut; one is never created from the device, which is how the
 * fleet got sites called "Singapore5".
 *
 * Duplicate-proof by three layers: the unique index on
 * vends.citybox_equipment_id, the Form Request rule, and unlinkedDevices()
 * never OFFERING a linked id.
 */
class DeviceProvisioningService
{
    public function __construct(
        private ChillerGateway $gateway,
        private DeviceSyncService $deviceSync,
        private CityboxDeviceRegistry $registry,
        private HistoryService $history,
    ) {}

    /**
     * Devices in their fleet that are NOT yet linked to a mark1 vend — the
     * Create-page dropdown source. Served from the citybox_devices registry,
     * which the poller keeps current every minute; only refreshed from their
     * API when the registry is stale/empty or the user presses Refresh.
     * Includes a "linked to vend X" annotation for the rest so the UI can
     * explain why an id is missing.
     *
     * Carries the machine ID each device WOULD import as (`machine_ids`), so the
     * dropdown can name the C-code ops know the fleet by instead of making them
     * pick a serial and read the preview card to find out.
     *
     * @return array{unlinked: Collection<int,ChillerDevice>, machine_ids: array<string,array{label:?string,error:?string}>, linked: array<string,int>}
     */
    public function devices(bool $fresh = false): array
    {
        $unlinked = $this->registry->unlinked($fresh);
        $linked = Vend::withoutGlobalScopes()->whereNotNull('citybox_equipment_id')
            ->pluck('id', 'citybox_equipment_id')->all();

        return [
            'unlinked' => $unlinked->values(),
            'machine_ids' => $this->machineIds($unlinked),
            'linked' => $linked,
        ];
    }

    /**
     * The machine ID each device would import as, keyed by equipment id — the same
     * rule machineIdFor() enforces at provision time, resolved for a whole list in
     * ONE query rather than a query per device. A device that cannot be imported
     * (placeholder name, or an ID another vend already holds) carries the reason.
     *
     * @param  Collection<int,ChillerDevice>  $devices
     * @return array<string,array{label:?string,error:?string}>
     */
    public function machineIds(Collection $devices): array
    {
        $codes = $devices->mapWithKeys(fn (ChillerDevice $d) => [$d->equipmentId => VendCode::fromExternalName($d->name)]);

        $labels = $codes->filter()->map(fn (VendCode $c) => $c->toLabel())->unique()->values()->all();
        $holders = [];
        if ($labels !== []) {
            foreach (Vend::withoutGlobalScopes()->where(fn ($q) => VendCode::whereLabels($q, $labels))->get(['id', 'code', 'code_prefix']) as $vend) {
                $holders[VendCode::label($vend->code_prefix, $vend->code)] = $vend->id;
            }
        }

        return $codes->map(function (?VendCode $code) use ($holders) {
            if (! $code) {
                return ['label' => null, 'error' => 'no machine ID in the OPS Pro name (expected like C6003)'];
            }
            $label = $code->toLabel();

            return [
                'label' => $label,
                'error' => isset($holders[$label]) ? "machine ID {$label} is already used by vend #{$holders[$label]}" : null,
            ];
        })->all();
    }

    /** One device by id from the registry (refreshed if stale), or null. */
    public function device(string $equipmentId): ?ChillerDevice
    {
        return $this->registry->find($equipmentId);
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

        $machineId = null;
        $machineIdError = null;
        if ($device) {
            try {
                $machineId = $this->machineIdFor($device)->toLabel();
            } catch (CityboxApiException $e) {
                $machineIdError = $e->getMessage();
            }
        }

        return [
            'device' => $device,
            'machine_id' => $machineId,
            'machine_id_error' => $machineIdError,
            'state' => $state,
            'product_count' => $productCount,
        ];
    }

    /**
     * Create the vend and, when a site was picked, bind it — atomically.
     *
     * @param  array{customer_id?:int|null, begin_date?:string|null, name?:string|null}  $site
     *                                                                                          customer_id  bind to this EXISTING site; omit to import unbound
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

            Operator::whereKey($operator->id)->lockForUpdate()->first();
            $machineId = $this->machineIdFor($device);

            $vend = Vend::create([
                'code' => $machineId->number,
                'code_prefix' => $machineId->prefix,
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

            $customer = ! empty($site['customer_id'])
                ? Customer::withoutGlobalScopes()->findOrFail((int) $site['customer_id'])
                : null;

            if ($customer) {
                $vend->forceFill(['customer_id' => $customer->id, 'binded_at' => now()])->save();
                $this->history->syncVendCustomerMovement($vend, $customer, true);
            }

            Log::info('Citybox vend provisioned', ['vend_id' => $vend->id, 'equipment_id' => $device->equipmentId, 'customer_id' => $customer?->id, 'user_id' => $by->id]);

            return $vend->refresh();
        });
    }

    public function operator(): Operator
    {
        return Operator::where('code', config('citybox.operator_code', 'CB'))->firstOr(function () {
            throw new CityboxApiException('Citybox operator not seeded — run CityboxOperatorSeeder');
        });
    }

    /**
     * The machine ID is OPS Pro's (Brian, 2026-09-19: "do not recreate another ID"):
     * their machine name "C6003" → code_prefix C, code 6003. Refused rather than
     * invented when the name carries no ID or another vend already holds it, so a
     * human fixes the name in OPS Pro. Only the (prefix, code) pair must be free —
     * the bare number may belong to an old vending machine (vends.code has no unique
     * index; terminal lookups use Vend::scopeBareCode). provision() locks the
     * operator row first, so concurrent provisions cannot both take one ID.
     *
     * @throws CityboxApiException
     */
    public function machineIdFor(ChillerDevice $device): VendCode
    {
        $machineId = VendCode::fromExternalName($device->name);
        if (! $machineId) {
            throw new CityboxApiException("OPS Pro machine name \"{$device->name}\" has no machine ID (expected like C6003) — rename it in OPS Pro, then Refresh.");
        }

        $holder = Vend::withoutGlobalScopes()
            ->where('code_prefix', $machineId->prefix)->where('code', $machineId->number)
            ->first(['id', 'citybox_equipment_id']);
        if ($holder) {
            throw new CityboxApiException("Machine ID {$machineId->toLabel()} is already used by vend #{$holder->id}".($holder->citybox_equipment_id ? " (CityBox {$holder->citybox_equipment_id})" : '').' — fix the duplicate name in OPS Pro.');
        }

        return $machineId;
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
