<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HidCard extends Model
{
    protected $fillable = [
        'email',
        'name',
        'operator_id',
        'value',
    ];

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function vends()
    {
        return $this->belongsToMany(Vend::class)->withTimestamps();
    }
}
