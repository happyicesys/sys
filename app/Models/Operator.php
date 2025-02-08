<?php

namespace App\Models;

use App\Models\Scopes\OperatorActiveScope;
use App\Models\Scopes\OperatorFilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Operator extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new OperatorActiveScope);
        static::addGlobalScope(new OperatorFilterScope);
    }

    protected $fillable = [
        'code',
        'country_id',
        'created_at',
        'created_by',
        'deactivated_at',
        'gst_vat_rate',
        'name',
        'is_active',
        'is_dcvend',
        'profile_id',
        'remarks',
        'timezone',
        'updated_by',
    ];

    // relationships
    public function address()
    {
        return $this->morphOne(Address::class, 'modelable')->whereNull('type');
    }

    public function contact()
    {
        return $this->morphOne(Contact::class, 'modelable');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function deliveryPlatformOperators()
    {
        return $this->hasMany(DeliveryPlatformOperator::class);
    }

    public function destinationAddresses()
    {
        return $this->hasMany(Address::class, 'modelable_id')->where('type', '2')->oldest();
    }

    public function externalOauthTokens()
    {
        return $this->morphMany(ExternalOauthToken::class, 'modelable');
    }

    public function operatorPaymentGateways()
    {
        return $this->hasMany(OperatorPaymentGateway::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function startAddresses()
    {
        return $this->hasMany(Address::class, 'modelable_id')->where('type', '1')->oldest();
    }

    public function vends()
    {
        return $this->hasMany(Vend::class)->orderBy('code');
    }

    // scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
