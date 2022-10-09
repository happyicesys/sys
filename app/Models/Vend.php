<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vend extends Model
{
    use HasFactory;

    protected $casts = [
        'temp_updated_at' => 'datetime'
    ];

    protected $fillable = [
        'code',
        'serial_num',
        'name',
        'temp',
        'temp_updated_at',
        'coin_amount',
        'firmware_ver',
        'is_door_open',
        'is_sensor_normal',
        'is_temp_error',
        'keylock_number',
        'vend_type_id',
    ];

    // relationships
    public function category()
    {
        return $this->morphOne(Category::class, 'modelable');
    }

    public function latestVendBinding()
    {
        return $this->hasOne(VendBinding::class)->latest('begin_date');
    }

    public function vendChannels()
    {
        return $this->hasMany(VendChannel::class)->orderBy('code');
    }

    public function vendTemps()
    {
        return $this->hasMany(VendTemp::class);
    }

    public function vendType()
    {
        return $this->belongsTo(VendType::class);
    }
}
