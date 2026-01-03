<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMovement extends Model
{
    use HasFactory;

    const TYPE_INCOMING = 1;
    const TYPE_ADJUSTMENT = 2;

    protected $fillable = [
        'product_id',
        'type',
        'qty',
        'operator_id',
        'remarks',
        'created_at',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
}
