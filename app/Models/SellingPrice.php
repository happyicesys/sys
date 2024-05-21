<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellingPrice extends Model
{
    use HasFactory;

    const TYPE_1 = 1;
    const TYPE_2 = 2;
    const TYPE_3 = 3;
    const TYPE_4 = 4;
    const TYPE_5 = 5;

    const TYPE_MAPPINGS = [
        self::TYPE_1 => 'RP1',
        self::TYPE_2 => 'RP2',
        self::TYPE_3 => 'RP3',
        self::TYPE_4 => 'RP4',
        self::TYPE_5 => 'RP5',
    ];

    protected $fillable = [
        'amount',
        'product_id',
        'type',
    ];

    protected $with = [
        'product.operator',
    ];

    // mutators and accessors
    protected function amount(): Attribute
    {
        return Attribute::make(
            // get: fn ($value) => $value/ 100,
            set: fn ($value) => $value * 100,
        );
    }

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
