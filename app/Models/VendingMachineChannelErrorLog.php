<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendingMachineChannelErrorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'vending_machine_channel_id',
        'vending_machine_channel_error_id',
    ];
}
