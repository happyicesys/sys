<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ProductMappingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_code',
        'product_id',
        'product_mapping_id',
        'server_amount',
    ];

    protected function serverAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value/ 100,
            set: fn ($value) => $value * 100,
        );
    }

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }
}
