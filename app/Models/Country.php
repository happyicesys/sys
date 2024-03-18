<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'currency_name',
        'currency_exponent',
        'currency_symbol',
        'map_id',
        'phone_code',
        'is_currency_exponent_hidden',
        'is_state',
        'sequence',
    ];

    const MAP_ENDPOINT = [
        'google' => 'https://www.google.com/maps/place/',
        'onemap' => 'https://www.onemap.gov.sg/api/common/elastic/search',
    ];

    // relationships
    public function quoteExchangeRates()
    {
        return $this->hasMany(ExchangeRate::class, 'quote_country_id')->latest();
    }

    public function latestQuoteExchangeRate()
    {
        return $this->hasOne(ExchangeRate::class, 'quote_country_id')->latest();
    }

    public function map()
    {
        return $this->belongsTo(Map::class);
    }
}
