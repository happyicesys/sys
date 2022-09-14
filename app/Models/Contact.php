<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Propaganistas\LaravelPhone\PhoneNumber;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone_num',
        'phone_country_id',
        'alt_phone_num',
        'alt_phone_country_id',
    ];

    // relationships
    public function modelable()
    {
        return $this->morphTo();
    }

    public function phoneCountry()
    {
        return $this->belongsTo(Country::class, 'phone_country_id');
    }

    public function altPhoneCountry()
    {
        return $this->belongsTo(Country::class, 'alt_phone_country_id');
    }

    // mutator and accessor
    protected function phoneNum(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => PhoneNumber::make(str_replace(' ', '', $profileData['contact']), $this->phoneCountry->code)->formatForCountry($this->phoneCountry->code),
        );
    }
}
