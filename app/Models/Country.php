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
        'phone_code',
        'is_state',
        'sequence',
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
}
