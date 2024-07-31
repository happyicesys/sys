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
        'cash_amount',
        'cashless_amount',
        'channels_json',
        'completed_at',
        'completed_by',
        'customer_id',
        'notes',
        'ops_job_id',
        'sequence',
        'status',
        'picked_at',
        'picked_by',
        'updated_by',
        'vend_id',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'picked_at' => 'datetime',
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

    public function pickedBy()
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class, 'vend_id');
    }

}
