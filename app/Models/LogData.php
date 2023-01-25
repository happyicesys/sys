<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogData extends Model
{
    use HasFactory;

    protected $fillable = [
        'value1',
        'value2',
        'type',
    ];

    protected $casts = [
        'value1' => 'json',
        'value2' => 'json',
    ];
}
