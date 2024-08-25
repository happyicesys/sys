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
        'updated_by',
        'vend_id',
        'vend_channel_record_id',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'flagged_at' => 'datetime',
        'picked_at' => 'datetime',
        'remarks_updated_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    // accessor and mutators
    // cash amount set x 100, get / 100
    public function cashAmount(): Attribute
    {
        return Attribute::make(
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

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
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

}
