<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsJobTask extends Model
{
    use HasFactory;

    const STATUS_PENDING   = 1;
    const STATUS_PICKED    = 2;
    const STATUS_COMPLETED = 3;

    const STATUS_MAPPINGS = [
        self::STATUS_PENDING   => 'Pending',
        self::STATUS_PICKED    => 'Picked',
        self::STATUS_COMPLETED => 'Completed',
    ];

    protected $fillable = [
        'ops_job_id',
        'status',
        'sequence',
        'task_name',
        'address',
        'postcode',
        'ops_note',
        'ref_url',
        'value',
        'qty',
        'latitude',
        'longitude',
        'created_by',
        'updated_by',
        'picked_at',
        'picked_by',
        'completed_at',
        'completed_by',
        'undo_picked_at',
        'undo_picked_by',
        'undo_completed_at',
        'undo_completed_by',
    ];

    protected $casts = [
        'status'            => 'integer',
        'sequence'          => 'float',
        'value'             => 'integer',
        'qty'               => 'integer',
        'latitude'          => 'float',
        'longitude'         => 'float',
        'picked_at'         => 'datetime',
        'completed_at'      => 'datetime',
        'undo_picked_at'    => 'datetime',
        'undo_completed_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Accessor / Mutator  (keep DB in cents, expose dollars in resources)
    // ------------------------------------------------------------------

    public function getValueAttribute($value): float
    {
        return $value / 100;
    }

    public function setValueAttribute($value): void
    {
        $this->attributes['value'] = (int) round($value * 100);
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function opsJob()
    {
        return $this->belongsTo(OpsJob::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function pickedBy()
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function undoPickedBy()
    {
        return $this->belongsTo(User::class, 'undo_picked_by');
    }

    public function undoCompletedBy()
    {
        return $this->belongsTo(User::class, 'undo_completed_by');
    }
}
