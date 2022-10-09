<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_country_id',
        'quote_country_id',
        'rate'
    ];

    // relationships
    public function baseCurrency()
    {
        return $this->belongsTo(Country::class, 'base_country_id');
    }

    public function quoteCurrency()
    {
        return $this->belongsTo(Country::class, 'quote_country_id');
    }

    // mutators
    protected function rate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => $value * 100,
        );
    }
}
