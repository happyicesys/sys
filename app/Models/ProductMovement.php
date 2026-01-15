<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMovement extends Model
{
    use HasFactory;

    const TYPE_INCOMING = 1;
    const TYPE_ADJUSTMENT = 2;
    const TYPE_PICKED = 3;
    const TYPE_UNDO_PICKED = 4;

    protected $fillable = [
        'product_id',
        'type',
        'qty',
        'operator_id',
        'user_id',
        'remarks',
        'batch_number',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
