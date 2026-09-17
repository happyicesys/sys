<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One daily setpoint entry for a smart freezer (night setback, pre-cool before a delivery).
 *
 * @see \App\Console\Commands\RunFreezerSetpointSchedules
 */
class FreezerSetpointSchedule extends Model
{
    protected $fillable = [
        'vend_id', 'run_at', 'celsius', 'is_active', 'last_run_on', 'last_command_id', 'created_by', 'created_by_name',
    ];

    protected $casts = [
        'celsius' => 'integer',
        'is_active' => 'boolean',
        'last_run_on' => 'date',
    ];

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class);
    }

    public function lastCommand(): BelongsTo
    {
        return $this->belongsTo(FreezerControlCommand::class, 'last_command_id');
    }

    /** "22:00" — the stored TIME without seconds. */
    public function runAtLabel(): string
    {
        return substr((string) $this->run_at, 0, 5);
    }
}
