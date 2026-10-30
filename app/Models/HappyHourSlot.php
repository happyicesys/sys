<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One hour (slot_minutes) of one freezer's Happy Hour: the SKU on offer and both prices, frozen when
 * the slot was planned. Written only by HappyHourPlanner; read by the menu, the AI card charge and
 * the campaign page.
 */
class HappyHourSlot extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ENDED = 'ended';

    /** Ended early: the SKU sold out (or fell under the stock floor) during its slot. */
    public const STATUS_SOLD_OUT = 'sold_out';

    /** Ended early or never ran: the campaign was edited, paused, deleted or the machine unbound. */
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'happy_hour_campaign_id', 'vend_id', 'product_id', 'slot_date', 'position', 'starts_at', 'ends_at', 'planned_ends_at',
        'original_price', 'promo_price', 'discount_pct', 'rank', 'is_priority', 'qty_at_pick', 'capacity_at_pick',
        'avg_daily_sales', 'days_of_cover', 'status', 'checked_at', 'units_sold', 'revenue_cents', 'discount_cents',
    ];

    protected $casts = [
        'slot_date' => 'date',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'planned_ends_at' => 'datetime',
        'checked_at' => 'datetime',
        'original_price' => 'integer',
        'promo_price' => 'integer',
        'discount_pct' => 'integer',
        'rank' => 'integer',
        'is_priority' => 'boolean',
        'position' => 'integer',
        'qty_at_pick' => 'integer',
        'capacity_at_pick' => 'integer',
        'avg_daily_sales' => 'float',
        'days_of_cover' => 'float',
        'units_sold' => 'integer',
        'revenue_cents' => 'integer',
        'discount_cents' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(HappyHourCampaign::class, 'happy_hour_campaign_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class)->withoutGlobalScopes();
    }

    /**
     * Until when a sale can still be this slot's: its PLANNED end. `ends_at` moves earlier when the slot is
     * cancelled, swapped or sold out, but a board that missed the nudge keeps charging the promo until then.
     */
    public function taggableUntil(): \Carbon\CarbonInterface
    {
        return $this->planned_ends_at !== null && $this->planned_ends_at->gt($this->ends_at) ? $this->planned_ends_at : $this->ends_at;
    }

    /** Whether a sale at $at is inside the slot, give or take $graceSeconds (up to its planned end). */
    public function covers(\Carbon\CarbonInterface $at, int $graceSeconds): bool
    {
        return $this->starts_at->copy()->subSeconds($graceSeconds)->lte($at)
            && $this->taggableUntil()->copy()->addSeconds($graceSeconds)->gt($at);
    }

    /** A slot that still offers its price (not ended, sold out or cancelled). */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }
}
