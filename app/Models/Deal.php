<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'qty_json',
        'amount',
        'product_id',
        'transaction_id',
    ];

    protected $casts = [
        'qty_json' => 'json',
    ];

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    // mutator and accessor
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value/ 100,
            set: fn ($value) => $value * 100,
        );
    }
}
