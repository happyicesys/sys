<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'alias',
        'uen',
        'base_currency_id',
    ];

    // relationships
    public function address()
    {
        return $this->morphOne(Address::class, 'modelable');
    }

    public function baseCurrency()
    {
        return $this->belongsTo(Country::class, 'base_currency_id');
    }

    public function contact()
    {
        return $this->morphOne(Contact::class, 'modelable');
    }

    public function profileTaxes()
    {
        return $this->hasMany(ProfileTax::class)->with('tax')->orderBy('sequence');
    }

    public function unitCosts()
    {
        return $this->hasMany(UnitCost::class);
    }
}
