<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendChannelError extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'desc'
    ];

    // protected $dates = [
    //     'created_at'
    // ];
    protected $casts = [
        'created_at' => 'datetime',
    ];
}
