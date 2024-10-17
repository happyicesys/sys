<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    const TYPE_BUY_ONE_FREE_ONE = 1;
    const TYPE_BUY_TWO_FREE_ONE = 2;
    const TYPE_BUNDLE = 3;

    protected $fillable = [
        'datetime_from',
        'datetime_to',
        'is_active',
        'is_control_by_group',
        'min_purchase_qty',
        'name',
        'promo_group_number',
        'promo_qty',
        'promo_value',
        'purchase_group_number',
        'type',
    ];
}
