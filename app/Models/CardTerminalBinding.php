<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Acquirer terminal unit (e.g. a NETS 8-digit TID) bound to a machine for a
 * date range. Settlement matching resolves the binding as of each report
 * row's transaction date — see CardSettlementMatcher.
 */
class CardTerminalBinding extends Model
{
    protected $fillable = [
        'provider',
        'terminal_id',
        'vend_id',
        'bound_from',
        'bound_until',
        'remarks',
    ];

    protected $casts = [
        'bound_from' => 'date',
        'bound_until' => 'date',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    /**
     * The TID sitting on this machine on `$date` (Y-m-d), or null. Newest
     * binding wins: a move closes the old row and opens the new one on the
     * SAME date and effectiveOn() is inclusive at both ends, so on a swap day
     * the two rows overlap by one day — the same tie-break CardSettlementMatcher
     * applies. This is what the sale rails freeze into
     * `vend_transactions.terminal_id` at write time.
     */
    public static function terminalIdOn(int $vendId, string $date): ?string
    {
        $binding = static::query()
            ->where('vend_id', $vendId)
            ->effectiveOn($date)
            ->orderByRaw('bound_from IS NULL, bound_from DESC')
            ->orderByDesc('id')
            ->first(['terminal_id']);

        return $binding?->terminal_id;
    }

    /** Bindings effective on the given date (null bounds = open-ended). */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('bound_from')->orWhere('bound_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('bound_until')->orWhere('bound_until', '>=', $date));
    }
}
