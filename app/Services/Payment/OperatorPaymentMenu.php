<?php

namespace App\Services\Payment;

use App\Models\Operator;
use App\Models\OperatorPaymentMethod;
use App\Models\PaymentMethod;
use App\Support\OptionCacheBuster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        if (! is_string($machineId) || ! preg_match('/^\d{1,9}$/', $machineId)) {
            return null;
        }

        $operatorId = DB::table('vends')
            ->where('code', (int) $machineId)
            ->whereNull('code_prefix') // a board reports the bare number (Vend::scopeBareCode)
            ->value('operator_id');

        return $operatorId ? (int) $operatorId : null;
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
