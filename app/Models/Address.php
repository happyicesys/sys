<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'block_num',
        'building',
        'city',
        'company_name',
        'country_id',
        'full_address',
        'latitude',
        'longitude',
        'postcode',
        'sequence',
        'street_name',
        'type',
        'unit_num',
    ];

    // relationships
    public function country()
    {
        return $this->belongsTo(Country::class)->orderBy('sequence');
    }

    public function modelable()
    {
        return $this->morphTo();
    }
}
