<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        // BIC / SWIFT code — CIMB bulk file detail column E for account
        // transfers (seeded by BankBicSeeder, editable on the Banks page).
        'bic_code',
        // PayNow proxy type (MOB/NRIC/UEN/VPA) for the pseudo PayNow banks — the
        // alternative CIMB column E when there's no BIC. Null for real banks.
        'proxy_type',
        'country_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
