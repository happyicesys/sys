<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Card terminal type (formerly CashlessProvider). Master list of card-terminal
 * manufacturer codes shown in the "Card Terminal" badge on the vend listing
 * pages. Values: CAS, NYX, PAX, 111, MLS.
 *
 * Renamed from `cashless_providers` → `card_terminals` on 2026-05-14.
 */
class CardTerminal extends Model
{
    use HasFactory;

    protected $table = 'card_terminals';

    protected $fillable = [
        'name',
        'remarks',
    ];

    public function vends()
    {
        return $this->hasMany(Vend::class, 'card_terminal_id');
    }

    /**
     * Legacy back-reference. `cashless_terminals.cashless_provider_id` was
     * the FK before the rename; the column name is kept for now (table is
     * being deprecated / truncated).
     */
    public function cashlessTerminals()
    {
        return $this->hasMany(CashlessTerminal::class, 'cashless_provider_id');
    }
}
