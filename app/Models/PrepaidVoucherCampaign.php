<?php

namespace App\Models;

use App\Services\HappyHour\HappyHourPriceStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operator's next-visit voucher settings (Brian, 2026-11-20), one row per operator. `is_active` is the
 * offer ("Pause" turns it off); vouchers already bought redeem while `is_redeem_enabled` holds, paused or
 * not. An HIPL row also covers the HIPL group's machines (PrepaidVoucherProgram).
 */
class PrepaidVoucherCampaign extends Model
{
    protected $fillable = [
        'name', 'operator_id', 'is_active', 'is_redeem_enabled', 'is_card_enabled', 'discount_pct', 'price_step_cents',
        'expiry_days', 'offer_seconds', 'excluded_product_ids', 'excluded_vend_ids', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_redeem_enabled' => 'boolean',
        'is_card_enabled' => 'boolean',
        'discount_pct' => 'integer',
        'price_step_cents' => 'integer',
        'expiry_days' => 'integer',
        'offer_seconds' => 'integer',
        'excluded_product_ids' => 'array',
        'excluded_vend_ids' => 'array',
    ];

    protected static function booted(): void
    {
        // The price step always fits the owner's currency, as on Happy Hour.
        static::saving(function (self $campaign) {
            if ($campaign->isDirty(['price_step_cents', 'operator_id'])) {
                $campaign->price_step_cents = HappyHourPriceStep::normalise(
                    HappyHourPriceStep::currencyOfOperator($campaign->operator_id),
                    $campaign->price_step_cents,
                );
            }
        });
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /** @return array<int, int> */
    public function excludedProductIds(): array
    {
        return array_values(array_map('intval', (array) ($this->excluded_product_ids ?? [])));
    }

    /** @return array<int, int> */
    public function excludedVendIds(): array
    {
        return array_values(array_map('intval', (array) ($this->excluded_vend_ids ?? [])));
    }
}
