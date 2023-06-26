<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendCriteriaScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'value',
        'vend_criteria_id',
        'vend_id',
        'weightage',
    ];
}
