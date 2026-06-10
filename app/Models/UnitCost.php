<?php

namespace App\Models;

use App\Traits\GetUserTimezone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitCost extends Model
{
    use GetUserTimezone, HasFactory;

    protected $fillable = [
        'cost',
        'product_id',
        'product_mapping_id',
        'profile_id',
        'date_from',
        'date_to',
        'is_current',
        'is_blended',
    ];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
        'is_blended' => 'boolean',
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

    // Blind SKU: when set, this is a derived/blended cost row for a parent
    // product within a specific mapping (normal rows leave this null).
    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function scopeBlended($query)
    {
        return $query->whereNotNull('product_mapping_id');
    }

    public function scopeNormal($query)
    {
        return $query->whereNull('product_mapping_id');
    }
}
