<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One operator's Grab facts for one product: its Grab sub-category and, in custom
 * pricing, its price (integer cents). Whether it is on Grab is not here: a store lists
 * whatever its machine holds. Written only by App\Services\Grab\Menu\GrabMenuService.
 */
class GrabMenuProduct extends Model
{
    protected $fillable = [
        'custom_amount',
        'operator_id',
        'previous_custom_amount',
        'product_id',
        'sub_category_json',
        'updated_by',
    ];

    protected $casts = [
        'custom_amount' => 'integer',
        'previous_custom_amount' => 'integer',
        'sub_category_json' => 'json',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
