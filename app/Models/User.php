<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasFilter;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasFilter, HasRoles, Notifiable;

    protected $guard_name = 'web';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'access_token',
        'name',
        'email',
        'is_active',
        'is_production_status_only',
        'operator_id',
        'password',
        'password_confirmation',
        'phone_country_id',
        'phone_number',
        'profile_id',
        'username',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_production_status_only' => 'boolean',
    ];

    // protected $with = ['vends'];

    // mutators
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => bcrypt($value),
        );
    }

    protected function profileId(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? $value : 1,
            // set: fn ($value) => $value ? $value : 1,
        );
    }

    public function getRedirectRoute()
    {
        // dd($this->toArray(), $this->roles()->first()->toArray());
        // return '/vends/customers';
        $currentRole = (int)$this->roles()->first()->id;

        // if($currentRole == 19 or $currentRole == 21) {
        //     return '/dashboard';
        // }else {
        //     return '/vends/customers';
        // }
        return match((int)$this->roles()->first()->id) {
            1 => '/vends/customers',
            2 => '/vends/customers',
            3 => '/vends/customers',
            4 => '/vends/customers',
            5 => '/vends/customers',
            6 => '/vends/customers',
            7 => '/vends/customers',
            8 => '/vends/customers',
            9 => '/vends/customers',
            10 => '/vends/customers',
            11 => '/vends/customers',
            12 => '/vends/customers',
            13 => '/vends/customers',
            14 => '/vends/customers',
            15 => '/vends/customers',
            16 => '/vends/customers',
            17 => '/vends/customers',
            18 => '/vends/customers',
            19 => '/dashboard',
            20 => '/vends/customers',
            21 => '/dashboard',
            22 => '/vends',
        };
    }

    // relationships
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function phoneCountry()
    {
        return $this->belongsTo(Country::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function vends()
    {
        return $this->belongsToMany(Vend::class);
    }

}
