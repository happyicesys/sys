<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Operator extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'country_id',
        'created_at',
        'created_by',
        'deactivated_at',
        'name',
        'is_active',
        'profile_id',
        'remarks',
        'updated_by',
    ];

    // relationships
    public function addresses()
    {
        return $this->morphOne(Address::class, 'modelable');
    }

    public function contact()
    {
        return $this->morphOne(Contact::class, 'modelable');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }
}
