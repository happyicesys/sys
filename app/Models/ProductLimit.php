<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'from_date',
        'is_created_by_system',
        'product_id',
        'qty',
        'created_by',
    ];

    protected $casts = [
        'date' => 'datetime',
        'from_date' => 'datetime',
    ];

    // relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
