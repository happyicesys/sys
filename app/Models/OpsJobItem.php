<?php

namespace App\Models;

use App\Traits\GetUserTimezone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsJobItem extends Model
{
    use GetUserTimezone, HasFactory;

    protected $fillable = [
        'acc_total_amount',
        'acc_total_cash_amount',
        'acc_total_cashless_amount',
        'acc_total_count',
        'acc_total_promo_amount',
        'cancelled_at',
        'cancelled_by',
        'cash_amount',
        'cashless_amount',
        'channels_json',
        'cms_transaction_at',
        'cms_transaction_by',
        'cms_transaction_id',
        'completed_at',
        'completed_by',
        'created_by',
        'customer_id',
        'flagged_at',
        'flagged_by',
        'is_cash_collected',
        'notes',
        'ops_job_id',
        'previous_ops_job_item_id',
        'remarks',
        'remarks_updated_at',
        'remarks_updated_by',
        'sequence',
        'status',
        // temporary
        'temp_cash_amount_from_vmc',
        'picked_at',
        'picked_by',
        'undo_completed_at',
        'undo_completed_by',
        'undo_flagged_at',
        'undo_flagged_by',
        'undo_picked_at',
        'undo_picked_by',
        'undo_verified_at',
        'undo_verified_by',
        'updated_by',
        'vend_id',
        'vend_channel_record_id',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cms_transaction_at' => 'datetime',
        'flagged_at' => 'datetime',
        'picked_at' => 'datetime',
        'remarks_updated_at' => 'datetime',
        'verified_at' => 'datetime',
        'undo_completed_at' => 'datetime',
        'undo_flagged_at' => 'datetime',
        'undo_picked_at' => 'datetime',
        'undo_verified_at' => 'datetime',
    ];

    // accessor and mutators
    // cash amount set x 100, get / 100
    public function cashAmount(): Attribute
    {
        return Attribute::make(
            // get: fn ($value) => $value != null ? $value / 100 : null,
            // set: fn ($value) => $value != null ? $value * 100 : null,
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    // cashless amount set x 100, get / 100
    public function cashlessAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    public function tempCashAmountFromVmc(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->oldest();
    }

    public function cmsTransactionBy()
    {
        return $this->belongsTo(User::class, 'cms_transaction_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function opsJob()
    {
        return $this->belongsTo(OpsJob::class);
    }

    public function opsJobItemChannels()
    {
        return $this->hasMany(OpsJobItemChannel::class);
    }

    public function pickedBy()
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function previousOpsJobItem()
    {
        return $this->belongsTo(OpsJobItem::class, 'previous_ops_job_item_id');
    }

    public function remarksUpdatedBy()
    {
        return $this->belongsTo(User::class, 'remarks_updated_by');
    }

    public function statusBy()
    {
        return $this->belongsTo(User::class, 'status_by');
    }

    public function undoCompletedBy()
    {
        return $this->belongsTo(User::class, 'undo_completed_by');
    }

    public function undoFlaggedBy()
    {
        return $this->belongsTo(User::class, 'undo_flagged_by');
    }

    public function undoPickedBy()
    {
        return $this->belongsTo(User::class, 'undo_picked_by');
    }

    public function undoVerifiedBy()
    {
        return $this->belongsTo(User::class, 'undo_verified_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class, 'vend_id');
    }

    public function vendChannelRecord()
    {
        return $this->belongsTo(VendChannelRecord::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

}
