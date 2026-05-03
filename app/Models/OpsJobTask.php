<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsJobTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'ops_job_id',
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
    ];

    protected $casts = [
        'sequence' => 'float',
        'value'    => 'integer',
        'qty'      => 'integer',
        'latitude' => 'float',
        'longitude'=> 'float',
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
}
