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
        'value'
    ];

    // mutator and accessor
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value/ 100,
            set: fn ($value) => $value * 100,
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
