<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One device's state at one poll. See migration for the two-table rationale. */
class CityboxInventoryPoll extends Model
{
    protected $fillable = [
        'vend_id', 'citybox_equipment_id', 'polled_at', 'online', 'device_status', 'products_seen',
        'total_qty', 'snapshot_json', 'movements_count', 'error', 'duration_ms',
    ];

    protected $casts = ['polled_at' => 'datetime', 'online' => 'boolean', 'snapshot_json' => 'array'];

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CityboxStockMovement::class, 'poll_id');
    }

    /** Previous SUCCESSFUL poll for the same vend (the diff baseline). */
    public static function previousFor(int $vendId, ?int $excludeId = null): ?self
    {
        return static::where('vend_id', $vendId)->whereNotNull('snapshot_json')
            ->when($excludeId, fn ($q) => $q->where('id', '<>', $excludeId))
            ->orderByDesc('polled_at')->orderByDesc('id')->first();
    }
}
