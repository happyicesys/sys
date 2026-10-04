<?php

namespace App\Models;

use App\Services\CardTerminal\CardTerminalEventLog;
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
 *   approved ──door closed + session ref (preauth, ai_capture)──▶ awaiting_ai
 *   awaiting_ai ──AI verdict / backstop──▶ captured (the judged total: the hold, then further
 *                                          charges of ≤ the hold each) | voided (nothing taken)
 *
 * `cancelling` exists because a cancel does not prove the customer did not tap:
 * the approval can land after it, and then the money must go back.
 *
 * `awaiting_ai`: the goods are released and the hold stays open until the kiosk
 * session's AI verdict decides the charge (AiCaptureDecision). Nothing on the device
 * moves it; only CardPaymentService::settleAwaitingAi does.
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

    /** Door closed on a hold; the charge waits for the session's AI verdict. */
    public const STATE_AWAITING_AI = 'awaiting_ai';

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
        'session_ref',
        'mode',
        'amount_cents',
        'captured_cents',
        'owed_cents',
        'state',
        'provider_status',
        'provider_txn_id',
        'payment_method',
        'last_error',
        'query_count',
        'last_queried_at',
        'approved_at',
        'door_closed_at',
        'cancel_requested_at',
        'captured_at',
        'voided_at',
        'resolved_at',
        'last_response',
        'ai_decision',
        'access_token',
    ];

    /** The Payrallel token the hold was made with (CardPaymentService::terminalOf); never serialised. */
    protected $hidden = ['access_token'];

    protected $casts = [
        'amount_cents' => 'integer',
        'captured_cents' => 'integer',
        'owed_cents' => 'integer',
        'query_count' => 'integer',
        'last_queried_at' => 'datetime',
        'approved_at' => 'datetime',
        'door_closed_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'captured_at' => 'datetime',
        'voided_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_response' => 'array',
        'ai_decision' => 'array',
        'access_token' => 'encrypted',
    ];

    /**
     * Every attempt and every state change lands on the trial timeline, whoever
     * made it (device, reconciler, a human in tinker) — one hook, no call site
     * can forget it.
     */
    protected static function booted(): void
    {
        static::created(function (self $intent) {
            app(CardTerminalEventLog::class)->record('intent.created', [
                'reference' => $intent->reference,
                'amount_cents' => $intent->amount_cents,
                'mode' => $intent->mode,
                'state' => $intent->state,
            ], $intent->terminal, $intent->custom_order_id);
        });

        static::updated(function (self $intent) {
            if (! $intent->wasChanged('state')) {
                return;
            }
            $from = $intent->getOriginal('state');
            app(CardTerminalEventLog::class)->record('intent.state', array_filter([
                'from' => $from,
                'to' => $intent->state,
                'provider_status' => $intent->provider_status,
                'payment_method' => $intent->payment_method,
                'provider_txn_id' => $intent->provider_txn_id,
                'error' => $intent->last_error,
                'seconds_since_created' => $intent->created_at?->diffInSeconds($intent->updated_at ?? now(), true),
                'queries' => $intent->query_count,
            ], fn ($v) => $v !== null), $intent->terminal, $intent->custom_order_id, null,
                in_array($intent->state, [self::STATE_VOID_FAILED, self::STATE_ERROR], true) ? 'error'
                    : (in_array($intent->state, [self::STATE_VOIDED, self::STATE_CANCELLING], true) ? 'warning' : 'info'));
        });
    }

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
