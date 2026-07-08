<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefundTicket extends Model
{
    use HasFactory, SoftDeletes;

    // ---- status machine ----
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_AUTO_RESOLVED = 'auto_resolved';            // Nayax external / already auto-refunded
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';
    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING_TRANSFER_INFO = 'pending_transfer_info';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_COMPLETED = 'completed';

    // ---- system recommendation ----
    const REC_PROCEED = 'proceed';
    const REC_REVIEW = 'review';
    const REC_REJECT = 'reject';

    // ---- payment channel ----
    const CHANNEL_QR = 'qr';
    const CHANNEL_NAYAX = 'nayax';
    const CHANNEL_OTHER_POS = 'other_pos';
    const CHANNEL_UNKNOWN = 'unknown';

    // ---- refund method ----
    const METHOD_PAYNOW = 'paynow';
    const METHOD_PAYPAL = 'paypal';
    const METHOD_NAYAX_AUTO = 'nayax_auto';
    const METHOD_NONE = 'none';

    protected $fillable = [
        'reference',
        'vend_code',
        'vend_id',
        'operator_id',
        'vend_transaction_id',
        'payment_gateway_log_id',
        'order_id',
        'reason_code',
        'reason_text',
        'manual_items_summary',
        'manual_pay_method',
        'refund_method',
        'payout_destination',
        'payout_meta_json',
        'contact_name',
        'contact_email',
        'contact_phone',
        'claimed_amount_cents',
        'final_refund_amount_cents',
        'final_refund_remarks',
        'status_override_remarks',
        'status_overridden_at',
        'is_manual',
        'entered_day',
        'entered_amount_cents',
        'approx_time',
        'payment_channel',
        'is_auto_refund_channel',
        'system_recommendation',
        'system_validation_json',
        'auto_refund_detected',
        'is_dropped',
        'status',
        'ops_verified_by',
        'ops_verified_at',
        'ops_remarks',
        'scheduled_at',
        'payout_batch_id',
        'paid_at',
        'completed_at',
        'last_email_template',
        'last_email_sent_at',
        'email_message_id',
        'email_thread_subject',
        'submit_ip',
        'is_repeat',
        'replicated_from_reference',
    ];

    protected $casts = [
        'payout_meta_json' => 'array',
        'system_validation_json' => 'array',
        'is_manual' => 'boolean',
        'is_auto_refund_channel' => 'boolean',
        'auto_refund_detected' => 'boolean',
        'is_dropped' => 'boolean',
        'is_repeat' => 'boolean',
        'claimed_amount_cents' => 'integer',
        'final_refund_amount_cents' => 'integer',
        'entered_amount_cents' => 'integer',
        'status_overridden_at' => 'datetime',
        'ops_verified_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_email_sent_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(RefundTicketItem::class);
    }

    public function attachments()
    {
        return $this->hasMany(RefundTicketAttachment::class);
    }

    public function logs()
    {
        return $this->hasMany(RefundTicketLog::class)->latest('created_at');
    }

    public function batch()
    {
        return $this->belongsTo(RefundPayoutBatch::class, 'payout_batch_id');
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class, 'vend_id');
    }

    public function vendTransaction()
    {
        return $this->belongsTo(VendTransaction::class, 'vend_transaction_id');
    }

    public function amountDisplay(): string
    {
        return number_format($this->claimed_amount_cents / 100, 2);
    }

    /**
     * The amount we will ACTUALLY pay out: the admin's final override when set,
     * otherwise the customer's original claim. This is the single source of truth
     * for every payout surface (settlement totals, CIMB/PayPal/PayNow files). NULL
     * final_refund_amount_cents means "not overridden" so untouched tickets pay the
     * claimed amount exactly as before. Mirror this rule in raw SQL sums with
     * COALESCE(final_refund_amount_cents, claimed_amount_cents).
     */
    public function getPayoutAmountCentsAttribute(): int
    {
        return $this->final_refund_amount_cents !== null
            ? (int) $this->final_refund_amount_cents
            : (int) $this->claimed_amount_cents;
    }

    /** True when an admin has recorded a final amount that differs from the claim. */
    public function hasFinalAmountOverride(): bool
    {
        return $this->final_refund_amount_cents !== null
            && (int) $this->final_refund_amount_cents !== (int) $this->claimed_amount_cents;
    }

    /** Display ("12.30") of the effective payout amount. */
    public function payoutAmountDisplay(): string
    {
        return number_format($this->payout_amount_cents / 100, 2);
    }

    /**
     * True when there is nothing left to pay because the charge was already
     * refunded — the "third validation icon crossed" signal. Such a ticket must
     * NOT be approved for a manual payout — only rejected or dropped.
     *
     * Reads the FROZEN self-validation verdict (system_validation_json.already_refunded)
     * decided at submission and re-synced only on re-match / a genuine later
     * auto-refund. It deliberately does NOT read the live auto_refund_detected
     * column, which a later "Reject → no charge" sets as a reason marker and would
     * otherwise make a manual, no-charge claim look already-refunded. Legacy tickets
     * without the frozen key fall back to the old signals.
     */
    public function isAlreadyRefunded(): bool
    {
        $sv = $this->system_validation_json ?? [];

        if (array_key_exists('already_refunded', $sv)) {
            return (bool) $sv['already_refunded'];
        }

        // Legacy tickets predating the frozen key: fall back to the frozen
        // submission signal only (not the mutable auto_refund_detected column, which
        // a "Reject → no charge" sets as a reason marker). Genuinely auto-refunded
        // legacy tickets were the old "auto_resolved" ones, which the retirement
        // migration backfills with already_refunded = true.
        return (bool) ($sv['txn_already_refunded'] ?? false);
    }

    /** Statuses where a refund is locked in / paying out (blocks a second refund of the same txn). */
    const ACTIVE_REFUND_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_SCHEDULED,
        self::STATUS_COMPLETED,
        self::STATUS_AUTO_RESOLVED,
    ];

    /**
     * Another ticket already refunding the SAME transaction (by order_id /
     * vend_transaction_id / payment_gateway_log_id), if any. Used to block
     * double refunds. Returns null for manual tickets (no transaction identity).
     */
    public function conflictingRefund(): ?RefundTicket
    {
        $orderId = $this->order_id;
        $vtId = $this->vend_transaction_id;
        $logId = $this->payment_gateway_log_id;

        if (!$orderId && !$vtId && !$logId) {
            return null;
        }

        return static::where('id', '!=', $this->id)
            ->whereIn('status', self::ACTIVE_REFUND_STATUSES)
            ->where(function ($q) use ($orderId, $vtId, $logId) {
                if ($orderId) {
                    $q->orWhere('order_id', $orderId);
                }
                if ($vtId) {
                    $q->orWhere('vend_transaction_id', $vtId);
                }
                if ($logId) {
                    $q->orWhere('payment_gateway_log_id', $logId);
                }
            })
            ->first();
    }
}
