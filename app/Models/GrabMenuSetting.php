<?php

namespace App\Models;

use App\Services\Grab\Menu\GrabPricingMode;
use Illuminate\Database\Eloquent\Model;

/**
 * One operator's Grab pricing rule (Grab Menu & Prices). Written only by
 * App\Services\Grab\Menu\GrabMenuService.
 */
class GrabMenuSetting extends Model
{
    // RP2 is the tier machines usually sell at (Brian, 2026-11-01).
    const DEFAULT_RP_TYPE = SellingPrice::TYPE_2;

    const ROUND_UP_STEPS = [10, 100];

    protected $fillable = [
        'category_json',
        'multiplier_bp',
        'operator_id',
        'pricing_mode',
        'round_up_cents',
        'rp_type',
        'updated_by',
    ];

    protected $casts = [
        'category_json' => 'json',
        'multiplier_bp' => 'integer',
        'pricing_mode' => GrabPricingMode::class,
        'round_up_cents' => 'integer',
        'rp_type' => 'integer',
    ];

    protected $attributes = [
        'pricing_mode' => 'rp',
        'rp_type' => self::DEFAULT_RP_TYPE,
        'multiplier_bp' => 10000,
        'round_up_cents' => 10,
    ];

    // The operator's saved rule, or the default one (RP2, unsaved) when it has none yet.
    public static function forOperator(int $operatorId): self
    {
        return self::query()->firstOrNew(['operator_id' => $operatorId]);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
