<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One next-visit voucher: bought after a single full-price sale, kept against a mobile number, redeemed
 * once for the same SKU (free) or for its paid value towards another SKU. Expiry is the end of a local day
 * (PrepaidVoucherExpiry); an active voucher past `expires_at` is expired without any job touching it.
 */
class PrepaidVoucher extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_VOIDED = 'voided';

    public const MODE_SKU = 'sku';

    public const MODE_VALUE = 'value';

    public const RAIL_QR = 'qr';

    public const RAIL_CARD = 'card';

    protected $fillable = [
        'operator_id', 'prepaid_voucher_campaign_id', 'phone', 'product_id', 'list_price_cents', 'price_paid_cents',
        'discount_pct', 'pay_rail', 'bought_vend_id', 'bought_order_id', 'bought_vend_transaction_id',
        'payment_gateway_log_id', 'first_order_id', 'issued_at', 'expires_at', 'status', 'reserved_vend_id',
        'reserved_token', 'reserved_until', 'redeemed_at', 'redeemed_vend_id', 'redeemed_order_id',
        'redeemed_vend_transaction_id', 'redeemed_mode', 'redeemed_product_id', 'value_used_cents',
        'forfeited_cents', 'balance_paid_cents', 'voided_at', 'voided_by', 'void_reason', 'phone_purged_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'reserved_until' => 'datetime',
        'redeemed_at' => 'datetime',
        'voided_at' => 'datetime',
        'phone_purged_at' => 'datetime',
        'list_price_cents' => 'integer',
        'price_paid_cents' => 'integer',
        'discount_pct' => 'integer',
        'value_used_cents' => 'integer',
        'forfeited_cents' => 'integer',
        'balance_paid_cents' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function redeemedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'redeemed_product_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function boughtVend(): BelongsTo
    {
        return $this->belongsTo(Vend::class, 'bought_vend_id')->withoutGlobalScopes();
    }

    public function redeemedVend(): BelongsTo
    {
        return $this->belongsTo(Vend::class, 'redeemed_vend_id')->withoutGlobalScopes();
    }

    /** Usable now: active (or held by a reservation that has lapsed) and not past its expiry. */
    public function scopeUsable(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now ??= Carbon::now();

        return $query->where('expires_at', '>', $now)
            ->where(fn (Builder $q) => $q->where('status', self::STATUS_ACTIVE)
                ->orWhere(fn (Builder $r) => $r->where('status', self::STATUS_RESERVED)->where('reserved_until', '<', $now)));
    }

    public function isExpired(?CarbonInterface $now = null): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_RESERVED], true)
            && $this->expires_at->lte($now ?? Carbon::now());
    }

    /** What the page shows: active / reserved / redeemed / voided / expired. */
    public function displayStatus(?CarbonInterface $now = null): string
    {
        return $this->isExpired($now) ? 'expired' : $this->status;
    }

    /** "+65 9123 ••67": enough for staff to confirm with a caller, not enough to read off a screen. */
    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        if (! preg_match('/^\+(\d{1,3}?)(\d{6,12})$/', $phone, $m)) {
            return '••••';
        }
        // Country codes the estate uses are 2 digits (65, 60, 62, 66).
        $cc = substr($phone, 1, 2);
        $national = substr($phone, 3);
        $len = strlen($national);

        return '+'.$cc.' '.substr($national, 0, min(4, $len - 2)).' '.str_repeat('•', max(0, $len - min(4, $len - 2) - 2)).substr($national, -2);
    }
}
