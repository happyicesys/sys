<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMovementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_movement_id',
        'product_id',
        'qty_json',
    ];

    protected $casts = [
        'qty_json' => 'json',
    ];

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productMovement()
    {
        return $this->belongsTo(ProductMovement::class);
    }
}
