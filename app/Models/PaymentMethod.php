<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * `menu_variant` NULL = the normal menu; a variant (e.g. "mindef") = normal + its own methods. An operator with
     * its own choices (OperatorPaymentMenu) has a key of its own; every other operator shares the normal one.
     */
    public static function menuCacheKey(string $countryCode, string $gatewayName, ?string $menuVariant = null, ?int $operatorId = null): string
    {
        return "payment_merchants:{$countryCode}:{$gatewayName}"
            .($menuVariant !== null ? ":{$menuVariant}" : '')
            .($operatorId !== null ? ":op{$operatorId}" : '');
    }

    /** Shown to every operator unless one switches it off; an opt-in method (a menu variant) only where switched on. */
    public function isShownByDefault(): bool
    {
        return $this->menu_variant === null;
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
