<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentMethod extends Model
{
    /** `code` of Cash (coins / notes at the machine) — every other method is cashless. */
    public const CODE_CASH = 0;

    /** `code` of the Card Terminal method (NETS reader). The ONLY method the NETS settlement report can carry. */
    public const CODE_CARD_TERMINAL = 1;

    use HasFactory;

    protected $fillable = [
        'code',
        'is_active',
        'is_apk_constant',
        'menu_variant',
        'name',
        'payment_gateway_id',
        'payment_merchant_id',
        'sequence',
        'type_name',
    ];

    /**
     * Cache key of the machine payment menu (PaymentController::getPaymentMerchantsApi).
     * `menu_variant` NULL = the normal menu; a variant (e.g. "mindef") = normal + its own methods.
     */
    public static function menuCacheKey(string $countryCode, string $gatewayName, ?string $menuVariant = null): string
    {
        return "payment_merchants:{$countryCode}:{$gatewayName}".($menuVariant !== null ? ":{$menuVariant}" : '');
    }

    /**
     * The menu variant of a machine's operator (`operators.payment_menu_variant`), from the bare machine
     * number mqtt-config appends to the menu URL as `?machineID=` (2026-10-29). Read fresh on every call —
     * one indexed lookup per board boot — so a machine moved to another operator follows at its next boot.
     * Anything that is not a plain number, an unknown machine or an operator with no variant = null, the
     * normal menu: a board must never lose its menu over this.
     */
    public static function menuVariantForMachine(mixed $machineId): ?string
    {
        if (! is_string($machineId) || ! preg_match('/^\d{1,9}$/', $machineId)) {
            return null;
        }

        // Raw on purpose: this is a public, unauthenticated API, so no operator or active-operator scope applies.
        $variant = DB::table('vends')
            ->join('operators', 'operators.id', '=', 'vends.operator_id')
            ->where('vends.code', (int) $machineId)
            ->whereNull('vends.code_prefix') // a board reports the bare number (Vend::scopeBareCode)
            ->value('operators.payment_menu_variant');

        $variant = is_string($variant) ? strtolower(trim($variant)) : '';

        return $variant !== '' ? $variant : null;
    }

    // relationships
    public function category()
    {
        return $this->morphOne(Category::class, 'modelable');
    }

    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function paymentMerchant()
    {
        return $this->belongsTo(PaymentMerchant::class);
    }
}
