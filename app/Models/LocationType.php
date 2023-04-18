<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequence',
        'name',
        'remarks',
    ];

    // relationships
    public function customer()
    {
        return $this->hasOne(Customer::class);
    }
}
