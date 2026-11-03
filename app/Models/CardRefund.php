<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One refund of a captured T05 charge (CardRefundService owns every write).
 *
 *   sending ──2xx──▶ refunded
 *      │────4xx / never connected──▶ refused     (nothing happened; try again)
 *      └────timeout / 5xx / run died─▶ uncertain  (may have gone through: no further refund on the
 *                                                 charge until a person checks Payrallel and says)
 *
 * `sending` is written before the call, so a run that dies mid-call still blocks a second refund.
 */
class CardRefund extends Model
{
    public const KIND_ITEM = 'item';

    public const KIND_FULL = 'full';

    public const STATE_SENDING = 'sending';

    public const STATE_REFUNDED = 'refunded';

    public const STATE_REFUSED = 'refused';

    public const STATE_UNCERTAIN = 'uncertain';

    /** Still open: nothing more may be sent on this charge until these are settled. */
    public const OPEN_STATES = [self::STATE_SENDING, self::STATE_UNCERTAIN];

    /** Money that has gone back, or may have. */
    public const COUNTED_STATES = [self::STATE_SENDING, self::STATE_UNCERTAIN, self::STATE_REFUNDED];

    protected $fillable = [
        'card_payment_intent_id', 'vend_transaction_id', 'kind', 'product_id', 'product_name', 'qty', 'unit_cents',
        'amount_cents', 'state', 'provider_refund_id', 'error', 'response', 'requested_by', 'requested_by_name',
        'resolved_by_name', 'resolved_at',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_cents' => 'integer',
        'amount_cents' => 'integer',
        'response' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function intent(): BelongsTo
    {
        return $this->belongsTo(CardPaymentIntent::class, 'card_payment_intent_id');
    }
}
