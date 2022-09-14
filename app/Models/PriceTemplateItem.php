<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'price_template_id',
        'quote_price',
        'retail_price',
        'sequence',
    ];

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function priceTemplate()
    {
        return $this->belongsTo(PriceTemplate::class);
    }

    // mutator and accessor
    protected function quotePrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value/ 100,
            set: fn ($value) => $value * 100,
        );
    }

    protected function retailPrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value/ 100,
            set: fn ($value) => $value * 100,
        );
    }
}
