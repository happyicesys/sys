<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A card terminal that mark1 commands through its provider's cloud (Payrallel),
 * bound to one machine. Holds that terminal's own access token — the provider
 * issues one token per terminal — encrypted at rest and hidden from arrays.
 *
 * Not a NETS terminal: those are card_terminal_units, reconciled from the NETS
 * settlement CSV. A remote terminal is reconciled per attempt by our own order
 * id (card_payment_intents), so the two never share a table.
 */
class RemoteCardTerminal extends Model
{
    public const PROVIDER_PAYRALLEL = 'payrallel';

    protected $fillable = [
        'vend_id',
        'card_terminal_unit_id',
        'provider',
        'label',
        'access_token',
        'is_active',
        'last_online',
        'last_state',
        'last_status_at',
    ];

    protected $hidden = ['access_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'is_active' => 'boolean',
        'last_online' => 'boolean',
        'last_status_at' => 'datetime',
    ];

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class);
    }

    /** The Data Management unit (SN + token) it was bound from; null for a command-line binding. */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(CardTerminalUnit::class, 'card_terminal_unit_id');
    }

    public function intents(): HasMany
    {
        return $this->hasMany(CardPaymentIntent::class);
    }

    /**
     * The Operation Dashboard's `t05_status` column: the bound terminal's last answer packed as
     * "id|online|state|checked at" (VendResource::t05Status unpacks it). A correlated lookup on the unique vend_id,
     * one per RETURNED row: that query is join-sensitive (VendController::indexCustomer).
     */
    public static function dashboardStatusSql(string $vendIdColumn = 'vends.id'): string
    {
        return "(SELECT CONCAT_WS('|', r.id, IFNULL(r.last_online, ''), IFNULL(r.last_state, ''),
                    IFNULL(DATE_FORMAT(r.last_status_at, '%Y-%m-%d %H:%i:%s'), ''))
                FROM remote_card_terminals r
                WHERE r.vend_id = {$vendIdColumn} AND r.is_active = 1)";
    }

    /** The active terminal bound to a vend, or null when it sells no remote-terminal card. */
    public static function activeForVend(Vend $vend): ?self
    {
        return static::query()->where('vend_id', $vend->id)->where('is_active', true)->first();
    }
}
