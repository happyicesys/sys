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
        'alias',
        'email',
        'is_active',
        'is_production_status_only',
        'operator_id',
        'password',
        'password_confirmation',
        'phone_country_id',
        'product_access_mode',
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
            get: fn($value) => $value,
            set: fn($value) => bcrypt($value),
        );
    }

    protected function profileId(): Attribute
    {
        return Attribute::make(
            set: fn($value) => $value ? $value : 1,
            // set: fn ($value) => $value ? $value : 1,
        );
    }

    public function getRedirectRoute()
    {
        // dd($this->toArray(), $this->roles()->first()->toArray());
        // return '/vends/customers';
        // Query the first role ONCE (was queried twice — once for the unused
        // $currentRole and once for the match). Same value, same null behavior.
        $role = $this->roles()->first();

        // No role at all would fatal on ->id below. Land them somewhere rather
        // than 500.
        if (! $role) {
            return '/vends/customers';
        }

        // Name-based first. The id match below is a hard-coded list, so ANY role
        // created after it was written silently falls through to
        // /vends/customers - and a role without `read vend-customers` then gets
        // a bare 403 straight after logging in, with no way back. That is
        // exactly how prod_owner (role 25, which only holds
        // read/export vend-customers-lite) ended up dead on arrival.
        // Match on name, not id: ids differ between local, staging and live.
        $routeByRoleName = [
            'prod_owner' => '/vends/customers-lite',
        ];

        if (isset($routeByRoleName[$role->name])) {
            return $routeByRoleName[$role->name];
        }

        $currentRole = (int) $role->id;

        // if($currentRole == 19 or $currentRole == 21) {
        //     return '/dashboard';
        // }else {
        //     return '/vends/customers';
        // }
        return match ($currentRole) {
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
            default => '/vends/customers',
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

    /**
     * "Access Product(s)" allow-list. NOT ownership - it is the set of SKUs
     * this user is permitted to see, capped by their operator's own list.
     *
     * Only meaningful when product_access_mode === 'list'.
     *
     * WARNING: Product carries OperatorProductFilterScope, so reading this
     * relation returns rows filtered by the VIEWER's operator, not the
     * subject's. App\Support\ProductAccess therefore reads the pivot table
     * directly and never goes through here. Use this relation for UI display
     * and eager-loading only.
     */
    public function accessProducts()
    {
        return $this->belongsToMany(Product::class)->orderBy('code');
    }

}
