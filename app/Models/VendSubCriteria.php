<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendSubCriteria extends Model
{
    use HasFactory;

    protected $fillable = [
        'desc',
        'name',
        'options_json',
        'sequence',
        'value',
        'vend_criteria_id',
        'weightage',
    ];

    protected $casts = [
        'options_json' => 'array',
    ];

    public function vendCriteria()
    {
        return $this->belongsTo(VendCriteria::class);
    }
}
