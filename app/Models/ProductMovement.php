<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'batch',
        'date',
        'remarks',
        'type'
    ];

    // relationships
    public function productMovementItems()
    {
        return $this->hasMany(ProductMovementItem::class);
    }
}
