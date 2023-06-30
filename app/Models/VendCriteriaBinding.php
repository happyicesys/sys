<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class VendCriteriaBinding extends Pivot
{
    use HasFactory;

    protected $fillable = [
        'weightage',
        'vend_criteria_id',
        'vend_sub_criteria_id',
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

    public function vendSubCriteria()
    {
        return $this->belongsTo(VendSubCriteria::class);
    }
}
