<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignItem extends Model
{
    use HasFactory;

    const PROMO_TYPE_AMOUNT = 1;
    const PROMO_TYPE_PERCENTAGE = 2;

    const PROMO_TYPE_MAPPINGS = [
        self::PROMO_TYPE_AMOUNT => 'Amount',
        self::PROMO_TYPE_PERCENTAGE => 'Percentage',
    ];

    // Campaign Types
    const CAMPAIGN_TYPE_A1 = 1; // Buy X labeled items -> discount (rate/absolute/amount)
    const CAMPAIGN_TYPE_A2 = 2; // Buy X labeled items -> free Z labeled item(s)
    const CAMPAIGN_TYPE_B1 = 3; // Cart total >= J -> discount (rate/absolute/amount)
    const CAMPAIGN_TYPE_B2 = 4; // Cart total >= J -> free Z labeled item(s)

    // Action Types
    const ACTION_TYPE_DISCOUNT_RATE = 1;       // percentage
    const ACTION_TYPE_ABSOLUTE_PRICE = 2;      // absolute discounted price
    const ACTION_TYPE_DISCOUNT_AMOUNT = 3;     // amount off
    const ACTION_TYPE_FREE_ITEM = 4;           // free labeled item(s)

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
    ];

    protected $fillable = [
        'apk_setting_id',
        'date_from',
        'date_to',
        'name',
        'remarks',
        'qty',
        'promo_type',
        'value',
        // new fields for extended campaign mechanics
        'uuid',
        'is_active',
        'campaign_type',
        'action_type',
        'action_value',
        'cart_amount_threshold',
        'free_qty',
        'selection_strategy',
    ];

    // mutator and accessor
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value/ 100,
            set: fn ($value) => $value * 100,
        );
    }

    // use cents storage for action_value to avoid float issues
    protected function actionValue(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? $value / 100 : null,
            set: fn ($value) => $value !== null ? (int) round($value * 100) : null,
        );
    }

    // cart amount threshold also in cents
    protected function cartAmountThreshold(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? $value / 100 : null,
            set: fn ($value) => $value !== null ? (int) round($value * 100) : null,
        );
    }

    public function tagBinding()
    {
        return $this->morphOne(TagBinding::class, 'modelable');
    }

    public function tagBindings()
    {
        return $this->morphMany(TagBinding::class, 'modelable');
    }
}
