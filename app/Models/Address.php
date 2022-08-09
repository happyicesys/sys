<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_name',
        'unit_num',
        'street_name',
        'postcode',
        'city',
        'country_id',
        'latitude',
        'longitude',
        'type',
        'sequence',
    ];

    // relationships
    public function modelable()
    {
        return $this->morphTo();
    }
}
