<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    use HasFactory;

    const COLORS = [
        '#37a2eb',
        '#ff6384',
        '#4cc1c0',
        '#ff9f40',
        '#9a66ff',
        '#ffcd56',
        '#c9cbcf'
    ];

    protected $fillable = [
        'color',
        'created_by',
        'desc',
        'name',
        'operator_id',
        'updated_by',
    ];

    public function modelable()
    {
        return $this->morphTo();
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
}
