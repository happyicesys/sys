<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperatorCallback extends Model
{
    use HasFactory;

    protected $fillable = [
        'operator_id',
        'code',
        'url',
        'format',
        'description',
    ];

    // relationships
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
}
