<?php

namespace App\Models;

use App\Models\Country;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    // mutators
    // protected function countryId(): Attribute
    // {
    //     return Attribute::make(
    //         set: fn ($value) => $value ? $value : Country::where('name', 'Singapore')->first()->id,
    //     );
    // }
}
