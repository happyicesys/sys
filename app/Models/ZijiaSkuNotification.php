<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One product-audit push from Zijia, kept whole, with what mark1 did (ZijiaSkuApprovalService). */
class ZijiaSkuNotification extends Model
{
    protected $fillable = [
        'raw_body', 'payload', 'verified', 'merchant_goods_code', 'product_code', 'sku_name',
        'audit_status', 'product_id', 'outcome',
    ];

    protected $casts = [
        'payload' => 'array',
        'verified' => 'boolean',
    ];
}
