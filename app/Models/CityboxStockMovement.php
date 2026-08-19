<?php

namespace App\Models;

use App\Enums\Citybox\MovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The chiller stock ledger: one row per (device, product) change between polls. Append-only. */
class CityboxStockMovement extends Model
{
    protected $fillable = [
        'vend_id', 'citybox_equipment_id', 'citybox_product_id', 'product_id', 'vend_channel_id',
        'poll_id', 'prev_poll_id', 'qty_before', 'qty_after', 'delta', 'movement_type',
        'occurred_between_start', 'occurred_between_end', 'ops_job_item_id',
    ];

    protected $casts = [
        'movement_type' => MovementType::class,
        'occurred_between_start' => 'datetime',
        'occurred_between_end' => 'datetime',
    ];

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class);
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(CityboxInventoryPoll::class, 'poll_id');
    }

    public function scopeForVend(Builder $q, int $vendId): Builder
    {
        return $q->where('vend_id', $vendId);
    }

    public function scopeSales(Builder $q): Builder
    {
        return $q->where('movement_type', MovementType::Sale->value);
    }

    public function scopeUnexplained(Builder $q): Builder
    {
        return $q->where('movement_type', MovementType::Unknown->value);
    }
}
