<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One card attempt on a remote terminal (CardPaymentService owns every write).
 *
 * States, and the only transitions CardPaymentService makes:
 *
 *   pending ──sent──▶ processing ──tap──▶ approved ──capture──▶ captured
 *      │                 │    │              │
 *      │                 │    └─declined─▶ declined        └─void──▶ voided | void_failed
 *      │                 └──cancel──▶ cancelling ──declined/timeout──▶ cancelled
 *      │                                   └──late approval──▶ (auto void) voided | void_failed
 *      └──send failed──▶ error
 *
 * `cancelling` exists because a cancel does not prove the customer did not tap:
 * the approval can land after it, and then the money must go back.
 */
class CardPaymentIntent extends Model
{
    public const STATE_PENDING = 'pending';

    public const STATE_PROCESSING = 'processing';

    public const STATE_APPROVED = 'approved';

    public const STATE_DECLINED = 'declined';

    public const STATE_CANCELLING = 'cancelling';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_CAPTURED = 'captured';

    public const STATE_VOIDED = 'voided';

    public const STATE_VOID_FAILED = 'void_failed';

    public const STATE_ERROR = 'error';

    public const MODE_SALE = 'sale';

    public const MODE_PREAUTH = 'preauth';

    /** Still waiting on the provider — the reconciler keeps querying these. */
    public const UNRESOLVED_STATES = [self::STATE_PENDING, self::STATE_PROCESSING, self::STATE_CANCELLING];

    /** Nothing more will happen to the money. */
    public const FINAL_STATES = [
        self::STATE_DECLINED, self::STATE_CANCELLED, self::STATE_CAPTURED,
        self::STATE_VOIDED, self::STATE_ERROR,
    ];

    protected $fillable = [
        'vend_id',
        'remote_card_terminal_id',
        'provider',
        'reference',
        'custom_order_id',
        'mode',
        'amount_cents',
        'captured_cents',
        'state',
        'provider_status',
        'provider_txn_id',
        'payment_method',
        'last_error',
        'query_count',
        'last_queried_at',
        'approved_at',
        'cancel_requested_at',
        'captured_at',
        'voided_at',
        'resolved_at',
        'last_response',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'captured_cents' => 'integer',
        'query_count' => 'integer',
        'last_queried_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'captured_at' => 'datetime',
        'voided_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_response' => 'array',
    ];

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(RemoteCardTerminal::class, 'remote_card_terminal_id');
    }

    public function isUnresolved(): bool
    {
        return in_array($this->state, self::UNRESOLVED_STATES, true);
    }

    public function isFinal(): bool
    {
        return in_array($this->state, self::FINAL_STATES, true);
    }

    /** The provider-side order id: vend code + device reference, unique across the fleet. */
    public static function customOrderIdFor(Vend $vend, string $reference): string
    {
        return $vend->code.'-'.$reference;
    }
}
