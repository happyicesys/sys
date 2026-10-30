<?php

namespace App\Services\Payment;

use App\Jobs\Vend\PushPaymentMenu;
use App\Models\Operator;
use App\Models\OperatorPaymentMethod;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Support\OptionCacheBuster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Which payment options an operator's machines show on their QR menu (Brian, 2026-10-29: Operator → Edit →
 * Machine payment menu). A method's default comes from `payment_methods.menu_variant`: NULL = shown to every
 * operator, set = opt-in (Ace & Apex). An `operator_payment_methods` row records only where an operator differs
 * from that default, so an operator with no rows gets the normal menu, byte for byte. This class is the only
 * writer of those rows and the one place their rule lives — the menu API and the Operator page both read it.
 *
 * Two guards keep a board's menu usable: an operator that switches everything off gets the normal menu, and when
 * the gateway's default method is switched off the first method shown becomes the default.
 *
 * Delivery (Brian, 2026-10-30): a board keeps its wallet list in memory and used to re-read it only on start, so a
 * change waited for a restart. Every save now queues PAYMENTMENU to the operator's machines (PushPaymentMenu; app
 * 321+ re-reads at once), and every fetch is recorded on the vend (`payment_menu_*`), so the page can say which
 * machines already hold the current menu: same fingerprint as what the menu answers now = up to date.
 */
class OperatorPaymentMenu
{
    /**
     * The operator of a machine, from the bare number mqtt-config appends to the menu URL as `?machineID=`. Anything
     * that is not a plain number, or an unknown machine, is null — the normal menu: a board must never lose its menu
     * over this. Raw on purpose: the menu API is public, so no operator scope applies.
     */
    public function operatorIdForMachine(mixed $machineId): ?int
    {
        $operatorId = $this->machine($machineId)?->operator_id;

        return $operatorId ? (int) $operatorId : null;
    }

    /** The machine behind `?machineID=` (id + operator_id), or null — same rules as operatorIdForMachine(). */
    public function machine(mixed $machineId): ?object
    {
        if (! is_string($machineId) || ! preg_match('/^\d{1,9}$/', $machineId)) {
            return null;
        }

        return DB::table('vends')
            ->where('code', (int) $machineId)
            ->whereNull('code_prefix') // a board reports the bare number (Vend::scopeBareCode)
            ->first(['id', 'operator_id']);
    }

    /**
     * The menu a machine is served (GET /api/v1/payment-merchants/{country}/{gateway}[/{variant}]), cached 10 min per
     * key. A URL tail adds that variant's methods to the normal menu; otherwise an operator with choices gets its own.
     */
    public function menu(string $countryCode, string $gatewayName, ?string $menuVariant = null, ?int $operatorId = null): array
    {
        $menuVariant = $menuVariant !== null ? strtolower($menuVariant) : null;
        $choices = $menuVariant === null && $operatorId !== null ? $this->choices($operatorId) : [];
        $cacheKey = PaymentMethod::menuCacheKey($countryCode, $gatewayName, $menuVariant, $choices ? $operatorId : null);

        return Cache::remember($cacheKey, 600, function () use ($countryCode, $gatewayName, $menuVariant, $choices) {
            $paymentMethods = PaymentMethod::query()
                ->with([
                    'paymentGateway',
                    'paymentMerchant',
                ])
                ->whereHas('paymentGateway', function ($query) use ($countryCode, $gatewayName) {
                    $query
                        ->where('name', $gatewayName)
                        ->whereHas('country', function ($query) use ($countryCode) {
                            $query->where('code', $countryCode);
                        });
                })
                ->where('is_active', true)
                ->when(! $choices, function ($query) use ($menuVariant) {
                    $query->where(function ($query) use ($menuVariant) {
                        $query->whereNull('menu_variant');
                        if ($menuVariant !== null) {
                            $query->orWhere('menu_variant', $menuVariant);
                        }
                    });
                })
                ->orderByRaw('ISNULL(sequence), sequence ASC')
                ->get();

            if ($choices) {
                $paymentMethods = self::apply($paymentMethods, $choices);
            }

            if ($paymentMethods->isEmpty()) {
                return [];
            }

            // The gateway's default switched off by the operator: the first method shown takes its place.
            $fallbackDefaultId = $choices && ! $paymentMethods->contains(
                fn ($paymentMethod) => $paymentMethod->paymentGateway->default_payment_method_id === $paymentMethod->id
            ) ? $paymentMethods->first()->id : null;

            return $paymentMethods->map(function ($paymentMethod) use ($fallbackDefaultId) {
                return [
                    'name' => $paymentMethod->paymentMerchant->name,
                    'image_url' => $paymentMethod->paymentMerchant->image_url,
                    'slug' => $paymentMethod->type_name,
                    // Numeric PAY_TYPE the machine echoes back on the TRADE frame (e.g. 201
                    // = Omise PayNow). Card Terminal is 1; every gateway method carries its
                    // own code. Lets a smart freezer report the exact merchant it charged.
                    'code' => $paymentMethod->code,
                    'sequence' => $paymentMethod->sequence,
                    'timeout_seconds' => 150,
                    'is_default' => $paymentMethod->paymentGateway->default_payment_method_id === $paymentMethod->id
                        || $paymentMethod->id === $fallbackDefaultId,
                ];
            })->toArray();
        });
    }

    /** What identifies one exact menu reply: the same JSON a machine receives. */
    public static function fingerprint(array $menu): string
    {
        return md5(json_encode($menu));
    }

    /**
     * A machine fetched its menu: remember when, from which path and exactly what it was served. Never throws — the
     * menu reply must not depend on this. A plain update, so `vends.updated_at` (and the audit log) stay untouched.
     */
    public function recordFetch(int $vendId, string $path, array $menu): void
    {
        try {
            DB::table('vends')->where('id', $vendId)->update([
                'payment_menu_fetched_at' => now(),
                'payment_menu_hash' => self::fingerprint($menu),
                'payment_menu_path' => mb_substr($path, 0, 64),
            ]);
        } catch (\Throwable $e) {
            Log::warning('payment menu fetch not recorded', ['vend_id' => $vendId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Whether a machine holds the menu it would be served now: `current` (same fingerprint), `stale` (it fetched an
     * older menu since), `unknown` (no fetch recorded yet — tracking began 2026-10-30). Compared on the path it last
     * fetched, so a machine on another gateway or a variant URL is judged against its own menu.
     */
    public function deliveryState(Vend $vend): string
    {
        if (! $vend->payment_menu_hash || ! $vend->payment_menu_path) {
            return 'unknown';
        }

        $parts = explode('/', $vend->payment_menu_path);
        if (count($parts) < 2) {
            return 'unknown';
        }
        $variant = $parts[2] ?? null;
        $expected = $this->menu($parts[0], $parts[1], $variant, $variant === null ? (int) $vend->operator_id : null);

        return self::fingerprint($expected) === $vend->payment_menu_hash ? 'current' : 'stale';
    }

    /**
     * The operator's machines as Operator → Edit lists them under the menu: whether each holds the current menu, when
     * it last fetched, when mark1 last told it to re-read, and whether its app acts on that at once.
     */
    public function machinesFor(Operator $operator): array
    {
        return Vend::withoutGlobalScopes()
            ->with('customer:id,name')
            ->where('operator_id', $operator->id)
            ->where('machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE)
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('is_testing', true))
            ->orderBy('code')
            ->get()
            ->map(fn (Vend $vend) => [
                'id' => $vend->id,
                'code' => $vend->code,
                'site' => $vend->customer?->name,
                'apk_version' => $vend->reportedApkVersion() ?: null,
                'instant' => PushPaymentMenu::supports($vend),
                'state' => $this->deliveryState($vend),
                'fetched_at' => $vend->payment_menu_fetched_at ? Carbon::parse($vend->payment_menu_fetched_at)->toIso8601String() : null,
                'pushed_at' => $vend->payment_menu_pushed_at ? Carbon::parse($vend->payment_menu_pushed_at)->toIso8601String() : null,
            ])
            ->values()
            ->all();
    }

    /** The operator's choices: [payment_method_id => shown]. Empty = the normal menu. */
    public function choices(int $operatorId): array
    {
        return DB::table('operator_payment_methods')
            ->where('operator_id', $operatorId)
            ->pluck('is_shown', 'payment_method_id')
            ->map(fn ($shown) => (bool) $shown)
            ->all();
    }

    /**
     * The methods a machine shows, given its operator's choices, in the order given. Everything switched off = the
     * normal menu.
     */
    public static function apply(Collection $methods, array $choices): Collection
    {
        $shown = $methods->filter(fn (PaymentMethod $method) => $choices[$method->id] ?? $method->isShownByDefault())->values();

        return $shown->isNotEmpty()
            ? $shown
            : $methods->filter(fn (PaymentMethod $method) => $method->isShownByDefault())->values();
    }

    /** Switch one method on or off for an operator's machines. */
    public function set(Operator $operator, PaymentMethod $method, bool $shown, ?int $userId): void
    {
        if (! $this->gatewayIds($operator)->contains($method->payment_gateway_id)) {
            throw ValidationException::withMessages([
                'payment_method_id' => __('This payment option belongs to a payment gateway the operator does not use.'),
            ]);
        }

        // Through Eloquent, one row at a time, so UserLogger records who changed what.
        $row = OperatorPaymentMethod::query()
            ->where('operator_id', $operator->id)
            ->where('payment_method_id', $method->id)
            ->first();

        if ($shown === $method->isShownByDefault()) {
            $row?->delete();
        } else {
            $row ??= new OperatorPaymentMethod(['operator_id' => $operator->id, 'payment_method_id' => $method->id]);
            $row->fill(['is_shown' => $shown, 'updated_by' => $userId])->save();
        }

        OptionCacheBuster::forgetOperatorPaymentMenu($operator->id);
        PushPaymentMenu::schedule($operator->id);
    }

    /**
     * What Operator → Edit shows: one block per payment gateway the operator holds keys for, each method with its
     * default, the operator's choice and what its machines show now.
     */
    public function tableFor(Operator $operator): array
    {
        $choices = OperatorPaymentMethod::query()
            ->with('updatedBy:id,name')
            ->where('operator_id', $operator->id)
            ->get()
            ->keyBy('payment_method_id');
        $choiceMap = $choices->map(fn (OperatorPaymentMethod $row) => $row->is_shown)->all();

        return PaymentMethod::query()
            ->with(['paymentGateway.country', 'paymentMerchant'])
            ->whereIn('payment_gateway_id', $this->gatewayIds($operator))
            ->where('is_active', true)
            ->orderByRaw('ISNULL(sequence), sequence ASC')
            ->get()
            ->groupBy('payment_gateway_id')
            ->map(function (Collection $methods) use ($choices, $choiceMap) {
                $gateway = $methods->first()->paymentGateway;
                $onMenu = self::apply($methods, $choiceMap);
                $fallsBack = ! $methods->contains(fn (PaymentMethod $m) => $choiceMap[$m->id] ?? $m->isShownByDefault());

                return [
                    'gateway_id' => $gateway->id,
                    'gateway' => $gateway->name,
                    'country' => $gateway->country?->code,
                    'falls_back' => $fallsBack,
                    'shown_count' => $onMenu->count(),
                    'methods' => $methods->map(function (PaymentMethod $method) use ($choices, $gateway, $onMenu) {
                        $choice = $choices->get($method->id);

                        return [
                            'id' => $method->id,
                            'code' => $method->code,
                            'name' => $method->paymentMerchant?->name ?? $method->name,
                            'image_url' => $method->paymentMerchant?->image_url,
                            'slug' => $method->type_name,
                            'is_gateway_default' => $gateway->default_payment_method_id === $method->id,
                            'shown_by_default' => $method->isShownByDefault(),
                            'is_shown' => $choice ? $choice->is_shown : $method->isShownByDefault(),
                            'on_menu' => $onMenu->contains('id', $method->id),
                            'changed_by' => $choice?->updatedBy?->name,
                            'changed_at' => $choice?->updated_at?->toIso8601String(),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function gatewayIds(Operator $operator): Collection
    {
        return DB::table('operator_payment_gateways')
            ->where('operator_id', $operator->id)
            ->distinct()
            ->pluck('payment_gateway_id')
            ->map(fn ($id) => (int) $id);
    }
}
