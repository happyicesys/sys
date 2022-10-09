<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUom extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'uom_id',
        'is_base_uom',
        'is_transaction_uom',
        'value',
    ];

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class)->orderBy('sequence');
    }

    // mutators
    protected function isBaseUom(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => $value ? true : false,
        );
    }

    protected function isTransactionUom(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => $value ? true : false,
        );
    }
}
