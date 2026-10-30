<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One campaign as Grab lists it for a store (ongoing or upcoming). Written only by
 * App\Services\GrabCampaignMirror; Grab is the truth for what is live (plan §9.3).
 */
class DeliveryPlatformLiveCampaign extends Model
{
    const STATE_ONGOING = 'ongoing';

    const STATE_UPCOMING = 'upcoming';

    // Who made it, as the page shows it.
    const SOURCE_MARK1 = 'mark1';

    const SOURCE_LEFTOVER = 'leftover';

    const SOURCE_MERCHANT_APP = 'merchant_app';

    const SOURCE_GRAB_TEAM = 'grab_team';

    const SOURCE_UNKNOWN = 'unknown';

    // Ended rows are kept this long for the page's "Ended" view, then pruned.
    const KEEP_GONE_DAYS = 30;

    protected $fillable = [
        'campaign_json',
        'created_by',
        'delivery_platform_campaign_item_vend_id',
        'delivery_platform_operator_id',
        'delivery_platform_ref_number_id',
        'discount_type',
        'ends_at',
        'first_seen_at',
        'gone_at',
        'grab_campaign_id',
        'last_seen_at',
        'merchant_id',
        'name',
        'scope_type',
        'starts_at',
        'state',
    ];

    protected $casts = [
        'campaign_json' => 'json',
        'ends_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'gone_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'starts_at' => 'datetime',
    ];

    public function deliveryPlatformRefNumber()
    {
        return $this->belongsTo(DeliveryPlatformRefNumber::class);
    }

    public function deliveryPlatformCampaignItemVend()
    {
        return $this->belongsTo(DeliveryPlatformCampaignItemVend::class);
    }

    public function scopeLive($query)
    {
        return $query->whereNull('gone_at');
    }

    /**
     * mark1 made it and still wants it; mark1 made it but believes it ended (the D2 /
     * "same item in two campaigns" case); or someone else made it.
     */
    public function source(): string
    {
        $itemVend = $this->deliveryPlatformCampaignItemVend;
        if ($itemVend) {
            $wanted = $itemVend->is_active
                && (! $itemVend->datetime_to || $itemVend->datetime_to->isFuture())
                && $itemVend->deliveryProductMappingVend
                && ! $itemVend->deliveryProductMappingVend->end_date;

            return $wanted ? self::SOURCE_MARK1 : self::SOURCE_LEFTOVER;
        }

        $by = strtolower((string) $this->created_by);
        if (str_contains($by, 'merchant')) {
            return self::SOURCE_MERCHANT_APP;
        }
        if (str_contains($by, 'grab')) {
            return self::SOURCE_GRAB_TEAM;
        }

        return self::SOURCE_UNKNOWN;
    }
}
