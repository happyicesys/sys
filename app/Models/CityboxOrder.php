<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A CityBox chiller order as their `order_list` last reported it (CityboxOrderSync). Projected
 * into vend_transactions by CityboxOrderProjector; deducts stock once via ChillerStockLedger.
 */
class CityboxOrder extends Model
{
    protected $fillable = [
        'citybox_order_id', 'order_name', 'citybox_equipment_id', 'vend_id', 'order_status',
        'qty', 'money_cents', 'good_money_cents', 'refer', 'refer_type', 'pay_label',
        'ordered_at', 'paid_at', 'payload_hash', 'raw_json',
        'first_seen_at', 'last_seen_at', 'status_changed_at',
        'stock_deducted_json', 'stock_deducted_at', 'stock_note',
        'vend_transaction_id', 'projected_at', 'projection_error',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'stock_deducted_json' => 'array',
        'order_status' => 'integer',
        'refer_type' => 'integer',
        'ordered_at' => 'datetime',
        'paid_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'stock_deducted_at' => 'datetime',
        'projected_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CityboxOrderItem::class, 'order_id');
    }

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendTransaction(): BelongsTo
    {
        return $this->belongsTo(VendTransaction::class);
    }
}
