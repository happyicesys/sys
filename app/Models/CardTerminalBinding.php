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

    /** Bindings effective on the given date (null bounds = open-ended). */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('bound_from')->orWhere('bound_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('bound_until')->orWhere('bound_until', '>=', $date));
    }
}
