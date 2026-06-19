<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only history of Site status changes. One row per change, written by
 * CustomerController::update() (and the create path for the initial status).
 *
 *   status_id   — the status the site was set TO (Customer::STATUS_*).
 *   status_date — the effective date the user entered (Active / Removed Date),
 *                 or the auto-stamped Inactive Date; null for Potential / New.
 *   changed_by  — user who made the change (null for system/seeder).
 *   source      — 'user' | 'system' | 'seeder'.
 */
class CustomerStatusLog extends Model
{
    protected $table = 'customer_status_logs';

    protected $fillable = [
        'customer_id',
        'status_id',
        'status_date',
        'changed_by',
        'source',
    ];

    protected $casts = [
        'status_id' => 'integer',
        'status_date' => 'date',
    ];

    /**
     * Human-readable status label (e.g. "Active", "Removed"), resolved from the
     * single source of truth in Customer::STATUSES_MAPPING.
     */
    public function getStatusNameAttribute(): ?string
    {
        return Customer::STATUSES_MAPPING[$this->status_id] ?? null;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
