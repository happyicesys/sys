<?php

namespace App\Models;

use App\Traits\GetUserTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsJob extends Model
{
    use GetUserTimezone, HasFactory;

    const STATUS_PENDING = '1';
    const STATUS_PICKED = '2';
    const STATUS_DELIVERED = '3';
    const STATUS_VERIFIED = '4';
    const STATUS_FLAGGED = '98';
    const STATUS_CANCELLED = '99';

    const STATUS_MAPPINGS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PICKED => 'Picked',
        self::STATUS_DELIVERED => 'Stock In',
        self::STATUS_VERIFIED => 'Verified',
        self::STATUS_FLAGGED => 'Flagged',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'code',
        'created_by',
        'date',
        'delivered_by',
        'operator_id',
        'picked_at',
        'picked_by',
        'status',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'datetime',
        'picked_at' => 'datetime',
    ];

    // relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function finishAddresses()
    {
        return $this->morphMany(Address::class, 'modelable')->ofMany('type', '2');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function opsJobItems()
    {
        return $this->hasMany(OpsJobItem::class);
    }

    public function pickedBy()
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function startAddresses()
    {
        return $this->morphMany(Address::class, 'modelable')->ofMany('type', '1');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

}
