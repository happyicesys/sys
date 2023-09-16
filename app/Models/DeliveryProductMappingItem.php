<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMappingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'delivery_product_mapping_id',
        'product_mapping_id',
        'product_mapping_item_id',

    ];
}
