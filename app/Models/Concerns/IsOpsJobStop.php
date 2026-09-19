<?php

namespace App\Models\Concerns;

use App\Models\Customer;
use App\Models\OpsJob;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;

/**
 * What every machine-bound stop inside an ops job shares: the job it rides on,
 * the machine and site it is for, a place in the visiting order, and who made
 * it. Used by ServiceNotice and StockCheck. The model declares CODE_PREFIX.
 */
trait IsOpsJobStop
{
    public function opsJob()
    {
        return $this->belongsTo(OpsJob::class);
    }

    /**
     * Deliberately unscoped: the tenancy boundary of a stop is its ops job's
     * operator (enforced in the controllers), and a scoped relation would
     * render a blank machine for a job the viewer is already allowed to open.
     */
    public function vend()
    {
        return $this->belongsTo(Vend::class)->withoutGlobalScope(OperatorVendFilterScope::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getDisplayCodeAttribute(): string
    {
        return static::CODE_PREFIX.'-'.$this->code;
    }

    public function isPending(): bool
    {
        return (int) $this->status === static::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return (int) $this->status === static::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return (int) $this->status === static::STATUS_CANCELLED;
    }

    public function statusName(): string
    {
        return static::STATUS_MAPPINGS[(int) $this->status] ?? 'Pending';
    }
}
