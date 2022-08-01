<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendingMachineChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'qty',
        'capacity',
        'amount',
        'is_active',
        'vending_machine_id',
    ];

    // relationships
    public function vendingMachine()
    {
        return $this->belongsTo(VendingMachine::class);
    }
}
