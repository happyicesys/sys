<?php

namespace App\Models;

use App\Services\Grab\Menu\GrabPricingMode;
use Illuminate\Database\Eloquent\Model;

/**
 * One Grab store's own price rule, in place of its operator's (grab_menu_settings). Read through
 * App\Services\Grab\Menu\GrabPriceRuleResolver; written only by GrabStorePriceRuleService.
 */
class GrabStorePriceRule extends Model
{
    protected $fillable = [
        'delivery_platform_ref_number_id',
        'multiplier_bp',
        'note',
        'pricing_mode',
        'round_up_cents',
        'rp_type',
        'updated_by',
    ];

    protected $casts = [
        'multiplier_bp' => 'integer',
        'pricing_mode' => GrabPricingMode::class,
        'round_up_cents' => 'integer',
        'rp_type' => 'integer',
    ];

    public function deliveryPlatformRefNumber()
    {
        return $this->belongsTo(DeliveryPlatformRefNumber::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
