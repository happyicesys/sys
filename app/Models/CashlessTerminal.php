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
        'operator_id',
        'termination_date',
        'updated_by',
    ];

    // relationships

    /**
     * Card-terminal type (renamed from cashlessProvider on 2026-05-14).
     * Column is still `cashless_provider_id` — the rename is class-only.
     */
    public function cardTerminal()
    {
        return $this->belongsTo(CardTerminal::class, 'cashless_provider_id');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }
}
