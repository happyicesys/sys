<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_active',
        'is_control_by_group',
        'min_purchase_qty',
        'name',
        'promo_item_group',
        'promo_item_qty',
        'purchase_item_group',
    ];
}
