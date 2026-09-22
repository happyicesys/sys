<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-machine, per-day counter for non-sales hardware / diagnostic events.
 *
 * Rows are written by App\Jobs\Vend\IncrementVendDailyStat via an atomic
 * INSERT ... ON DUPLICATE KEY UPDATE, so prefer the job over $model->save()
 * to avoid lost increments under concurrency.
 *
 * Known metrics:
 *   - 'pwron'            : VendDataService receives Type=PWRON from a machine
 *   - 'ui_looper_stall'  : the APK's main looper stopped answering (frozen screen)
 *   - 'ui_no_frames'     : looper alive but nothing drawn — dead draw pipeline
 *   - 'ui_probe_fail'    : the hourly page round-trip did not complete
 *   - 'ui_probe_pass'    : it did — the denominator the three above are read against
 *
 * Reserved for future hardware/stat metrics — add new strings without a
 * schema migration.
 */
class VendDailyStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'vend_id',
        'vend_code',
        'date',
        'metric',
        'count',
    ];

    protected $casts = [
        'date' => 'date',
        'count' => 'integer',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
