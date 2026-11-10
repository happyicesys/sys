<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The typed Grab price (integer cents) of one product at one store whose own rule is custom. Written only by
 * App\Services\Grab\Menu\GrabStorePriceRuleService.
 */
class GrabStoreCustomPrice extends Model
{
    protected $fillable = [
        'amount',
        'delivery_platform_ref_number_id',
        'previous_amount',
        'product_id',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'previous_amount' => 'integer',
    ];
}
