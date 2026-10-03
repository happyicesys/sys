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

    /** The company row Payrallel remote terminals (T05) are filed under. */
    public const NAME_PAYRALLEL = 'Payrallel (T05)';

    protected $fillable = [
        'name',
        'remarks',
        'can_bind_multiple_vends',
    ];

    /**
     * can_bind_multiple_vends: one of this company's terminals may serve several machines
     * at once. Off (the default) = binding a terminal to a machine releases it from any other.
     */
    protected $casts = [
        'can_bind_multiple_vends' => 'boolean',
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
