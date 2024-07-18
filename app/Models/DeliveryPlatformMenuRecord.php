<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformMenuRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_platform_slug',
        'platform_ref_id',
        'ref_id',
        'request_json',
        'vend_code'
    ];

    protected $casts = [
        'request_json' => 'json',
    ];
}
