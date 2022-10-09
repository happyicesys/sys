<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashlessTerminal extends Model
{
    use HasFactory;

    protected $fillable = [
        'begin_date',
        'cashless_provider_id',
        'code',
        'created_by',
        'is_active',
        'termination_date',
        'updated_by',
    ];

    // relationships
    public function cashlessProvider()
    {
        return $this->belongsTo(CashlessProvider::class);
    }
}
