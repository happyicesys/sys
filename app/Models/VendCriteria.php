<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendCriteria extends Model
{
    use HasFactory;

    const OPERATOR_NAME = [
        '<=' => 'Less than or equal to',
        '>=' => 'Greater than or equal to',
        '=' => 'Equal to',
        '!=' => 'Not equal to',
        '<' => 'Less than',
        '>' => 'Greater than',
    ];

    protected $fillable = [
        'classname',
        'desc',
        'has_sub_criteria',
        'name',
        'operator',
        'options_json',
        'sequence',
        'value',
        'weightage',
    ];

    protected $casts = [
        'options_json' => 'array',
    ];

    public function modelable()
    {
        return $this->morphTo();
    }

    public function vends()
    {
        return $this->belongsToMany(Vend::class)->using(VendCriteriaBinding::class);
    }

    public function vendSubCriterias()
    {
        return $this->hasMany(VendSubCriteria::class);
    }
}
