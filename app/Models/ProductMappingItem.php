<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMappingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_code',
        'product_id',
        'product_mapping_id',
        'selling_price_id',
        'sequence',
        'server_amount',
    ];

    protected function serverAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
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

    /**
     * Refuse a human edit to this item when its mapping is a CityBox mirror.
     * Looks the mapping up unscoped: the operator global scope must not turn
     * "another operator's mirror" into "no mapping" and let the guard lapse.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assertMappingEditable(string $field = 'channel_code'): void
    {
        ProductMapping::withoutGlobalScopes()->find($this->product_mapping_id)?->assertEditable($field);
    }

    public function sellingPrice()
    {
        return $this->belongsTo(SellingPrice::class);
    }
}
