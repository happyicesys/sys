<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HidCard extends Model
{
    protected $fillable = [
        'operator_id',
        'value',
    ];

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
}
