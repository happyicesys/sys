<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class CustomerVendBinding extends Model
{
    protected $fillable = [
        'created_at',
        'customer_id',
        'is_binding',
        'user_id',
        'vend_id',
        'vend_prefix_id',
    ];

    protected $casts = [
        'is_binding' => 'boolean',
    ];

    // mutators
    protected function createdAt(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? $value->format('Y-m-d H:i:s') : null,
        );
    }

    // relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendPrefix()
    {
        return $this->belongsTo(VendPrefix::class);
    }

    public function scopeBinding($query)
    {
        return $query->where('is_binding', true);
    }
}
