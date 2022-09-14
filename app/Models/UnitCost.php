<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'cost',
        'product_id',
        'profile_id',
        'date_from',
        'date_to',
    ];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
    ];

    // mutator and accessor
    protected function cost(): Attribute
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

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
