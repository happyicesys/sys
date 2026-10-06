<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One step of a product's Zijia application: saved, sent, answered, called back, applied. */
class ZijiaSkuApplicationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['zijia_sku_application_id', 'event', 'level', 'detail', 'user_name', 'created_at'];

    protected $casts = [
        'detail' => 'array',
        'created_at' => 'datetime',
    ];
}
