<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class VendCriteriaBinding extends Pivot
{
    /**
     * `vend_criteria_bindings` has an auto-increment id, but Pivot switches `incrementing` off, so
     * the id was never read back after an insert. The audit logger (UserLogger) then
     * had no record id to file the 'created' row under and lost it — the trail showed
     * machines being removed and never added (prod 2026-09-21).
     */
    public $incrementing = true;

    use HasFactory;

    protected $fillable = [
        'weightage',
        'vend_criteria_id',
        // 'vend_sub_criteria_id',
        'vend_id',
        'value',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendCriteria()
    {
        return $this->belongsTo(VendCriteria::class);
    }

    // public function vendSubCriteria()
    // {
    //     return $this->belongsTo(VendSubCriteria::class);
    // }
}
