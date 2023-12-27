<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMappingBulkItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_product_mapping_bulk_id',
        'delivery_product_mapping_item_id',
        'qty',
        'sub_category_json',
    ];

    protected $casts = [
        'sub_category_json' => 'json',
    ];

    // relationships
    public function deliveryProductMappingBulk()
    {
        return $this->belongsTo(DeliveryProductMappingBulk::class);
    }

    public function deliveryProductMappingItem()
    {
        return $this->belongsTo(DeliveryProductMappingItem::class);
    }
}
