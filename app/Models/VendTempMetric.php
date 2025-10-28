<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendTempMetric extends Model
{
    use HasFactory;

    public const PERIOD_DAILY = 'daily';
    public const PERIOD_ALL_TIME = 'all_time';
    public const PERIOD_ROLLING_30 = 'rolling_30';
    public const PERIOD_ROLLING_60 = 'rolling_60';
    public const PERIOD_ROLLING_90 = 'rolling_90';

    protected $fillable = [
        'vend_id',
        'temp_type',
        'period_type',
        'period_key',
        'period_start',
        'period_end',
        'min_temp_value',
        'max_temp_value',
        'reading_count',
        'days_covered',
        'min_temp_recorded_at',
        'max_temp_recorded_at',
        'computed_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'min_temp_recorded_at' => 'datetime',
        'max_temp_recorded_at' => 'datetime',
        'computed_at' => 'datetime',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
