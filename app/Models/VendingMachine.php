<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendingMachine extends Model
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
    ];

    // relationships
    public function category()
    {
        return $this->morphOne(Category::class, 'modelable');
    }

    public function vendingMachineTemps()
    {
        return $this->hasMany(VendingMachineTemp::class);
    }
}
