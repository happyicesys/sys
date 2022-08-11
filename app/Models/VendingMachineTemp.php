<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendingMachineTemp extends Model
{
    use HasFactory;

    const TEMPERATURE_ERROR = 32767;

    protected $fillable = [
        'vending_machine_id',
        'value',
    ];

    // relationships
    public function vendingMachine()
    {
        return $this->belongsTo(VendingMachine::class);
    }
}
