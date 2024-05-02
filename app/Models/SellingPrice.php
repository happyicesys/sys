<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellingPrice extends Model
{
    use HasFactory;

    const TYPE_1 = 1;
    const TYPE_2 = 2;
    const TYPE_3 = 3;
    const TYPE_4 = 4;

    const TYPE_MAPPING = [
        self::TYPE_1 => 'SP 1',
        self::TYPE_2 => 'SP 2',
        self::TYPE_3 => 'SP 3',
        self::TYPE_4 => 'SP 4',
    ];

    protected $fillable = [
        'amount',
        'product_id',
        'type',
    ];

    protected $with = [
        'product.operator',
    ];

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
