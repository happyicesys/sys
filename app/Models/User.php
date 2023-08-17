<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'api';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'access_token',
        'name',
        'email',
        'operator_id',
        'password',
        'password_confirmation',
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
    ];

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
        // dd((int)$this->roles()->first()->id);
        $currentRole = (int)$this->roles()->first()->id;

        if($currentRole === 14) {
            return '/dashboard';
        }else {
            return '/vends';
        }
        // return match((int)$this->roles()->first()->id) {
        //     1 => '/vends',
        //     2 => '/vends',
        //     3 => '/vends',
        //     4 => '/vends',
        //     5 => '/vends',
        //     6 => '/vends',
        //     7 => '/vends',
        //     8 => '/vends',
        //     9 => '/vends',
        //     10 => '/vends',
        //     11 => '/vends',
        //     12 => '/vends',
        //     13 => '/vends',
        //     14 => '/dashboard',
        // };
    }

    // relationships
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

}
