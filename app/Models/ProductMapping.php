<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'remarks',
        'operator_id',
    ];

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
}
