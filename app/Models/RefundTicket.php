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
    const STATUS_PENDING_APPROVAL = 'pending_approval';
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
        'reason_code',
        'reason_text',
        'refund_method',
        'payout_destination',
        'payout_meta_json',
        'contact_email',
        'contact_phone',
        'claimed_amount_cents',
        'is_manual',
        'entered_day',
        'entered_amount_cents',
        'approx_time',
        'payment_channel',
        'is_auto_refund_channel',
        'system_recommendation',
        'system_validation_json',
        'auto_refund_detected',
        'status',
        'ops_verified_by',
        'ops_verified_at',
        'ops_remarks',
        'submitted_for_approval_by',
        'submitted_for_approval_at',
        'manager_approved_by',
        'manager_approved_at',
        'scheduled_at',
        'payout_batch_id',
        'paid_at',
        'completed_at',
        'last_email_template',
        'last_email_sent_at',
        'submit_ip',
    ];

    protected $casts = [
        'payout_meta_json' => 'array',
        'system_validation_json' => 'array',
        'is_manual' => 'boolean',
        'is_auto_refund_channel' => 'boolean',
        'auto_refund_detected' => 'boolean',
        'claimed_amount_cents' => 'integer',
        'entered_amount_cents' => 'integer',
        'ops_verified_at' => 'datetime',
        'submitted_for_approval_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_email_sent_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(RefundTicketItem::class);
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
}
