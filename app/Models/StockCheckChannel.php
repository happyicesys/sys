<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One drawn channel of a stock check. `system_qty` is what mark1 believed at
 * the moment of submit, `counted_qty` what the driver found, `variance_qty`
 * their difference (negative = short).
 */
class StockCheckChannel extends Model
{
    protected $fillable = [
        'stock_check_id', 'vend_channel_id', 'vend_channel_code', 'product_id', 'capacity', 'amount',
        'system_qty', 'counted_qty', 'variance_qty', 'note',
        'synced_at', 'synced_by', 'qty_before_sync', 'qty_after_sync',
    ];

    protected $casts = [
        'vend_channel_code' => 'integer',
        'capacity' => 'integer',
        'amount' => 'integer',
        'system_qty' => 'integer',
        'counted_qty' => 'integer',
        'variance_qty' => 'integer',
        'qty_before_sync' => 'integer',
        'qty_after_sync' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function stockCheck()
    {
        return $this->belongsTo(StockCheck::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function syncedBy()
    {
        return $this->belongsTo(User::class, 'synced_by');
    }

    public function isCounted(): bool
    {
        return $this->counted_qty !== null;
    }

    public function hasVariance(): bool
    {
        return $this->isCounted() && (int) $this->variance_qty !== 0;
    }

    public function isSynced(): bool
    {
        return $this->synced_at !== null;
    }

    /** Variance in integer cents at the price snapshotted when drawn. */
    public function varianceValueCents(): ?int
    {
        return $this->isCounted() ? (int) $this->variance_qty * (int) $this->amount : null;
    }
}
