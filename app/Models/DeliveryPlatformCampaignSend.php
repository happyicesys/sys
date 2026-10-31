<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One thing ConnectVend asked Grab about one store's campaign, and Grab's answer.
 * A log, never the truth for what runs (that is Grab's list, the mirror). Written only
 * by App\Services\GrabPromotionService.
 */
class DeliveryPlatformCampaignSend extends Model
{
    const ACTION_CREATE = 'create';

    const ACTION_UPDATE = 'update';

    const ACTION_END = 'end';

    const STATUS_PENDING = 'pending';

    const STATUS_SENDING = 'sending';

    const STATUS_OK = 'ok';

    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'action',
        'batch',
        'delivery_platform_operator_id',
        'delivery_platform_ref_number_id',
        'grab_campaign_id',
        'merchant_id',
        'request_json',
        'response_code',
        'response_json',
        'response_message',
        'sent_at',
        'status',
        'user_id',
    ];

    protected $casts = [
        'request_json' => 'json',
        'response_json' => 'json',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryPlatformRefNumber()
    {
        return $this->belongsTo(DeliveryPlatformRefNumber::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SENDING], true);
    }
}
