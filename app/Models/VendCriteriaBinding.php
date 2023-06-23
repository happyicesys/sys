<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class VendCriteriaBinding extends Pivot
{
    use HasFactory;

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendCriteria()
    {
        return $this->belongsTo(VendCriteria::class);
    }
}
