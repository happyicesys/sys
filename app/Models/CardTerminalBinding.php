<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Acquirer terminal unit (e.g. a NETS 8-digit TID) bound to a machine over a
 * time range [from_at, until_at) — to the second since 2026-09-25, because a
 * technician swaps a terminal at 14:30, not at midnight. Settlement matching
 * resolves each report line by its own time — see CardSettlementMatcher.
 *
 * bound_from / bound_until are DERIVED dates (the saving hook keeps them in
 * step) for the day-level screens and SQL; write from_at / until_at. A row
 * written with dates only (the importer, old callers) gets from = that day
 * 00:00 and until = the day after 00:00, the old inclusive-date meaning.
 *
 * `source`: manual (a person on Setting/Edit — authoritative from the moment
 * it was recorded, created_at), report (NETS evidence), import (seed CSV).
 */
class CardTerminalBinding extends Model
{
    protected $fillable = [
        'provider',
        'terminal_id',
        'vend_id',
        'bound_from',
        'bound_until',
        'from_at',
        'until_at',
        'source',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'bound_from' => 'date',
        'bound_until' => 'date',
        'from_at' => 'datetime',
        'until_at' => 'datetime',
    ];

    const SOURCE_MANUAL = 'manual';

    const SOURCE_REPORT = 'report';

    const SOURCE_IMPORT = 'import';

    protected static function booted(): void
    {
        static::saving(function (CardTerminalBinding $b) {
            // Dates only (importer, legacy callers) → the inclusive-day meaning.
            if ($b->isDirty('bound_from') && ! $b->isDirty('from_at')) {
                $b->from_at = $b->bound_from ? Carbon::parse($b->bound_from)->startOfDay() : null;
            }
            if ($b->isDirty('bound_until') && ! $b->isDirty('until_at')) {
                $b->until_at = $b->bound_until ? Carbon::parse($b->bound_until)->addDay()->startOfDay() : null;
            }
            // Times are the truth; the dates follow.
            $b->bound_from = $b->from_at ? Carbon::parse($b->from_at)->toDateString() : null;
            $b->bound_until = $b->until_at ? Carbon::parse($b->until_at)->subSecond()->toDateString() : null;
            if (! $b->source) {
                $b->source = $b->created_by ? self::SOURCE_MANUAL : self::SOURCE_REPORT;
            }
        });
    }

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }

    /** Covers the instant $at: from_at <= at < until_at (NULL = open). */
    public function coversAt(CarbonInterface $at): bool
    {
        return ($this->from_at === null || $this->from_at->lte($at))
            && ($this->until_at === null || $this->until_at->gt($at));
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    /**
     * The person who made this binding, or null when nothing human did — the
     * Card Settlement auto-match, the importer, any console path. The screens
     * render that null as "sys".
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** "sys" unless a person changed the terminal on the machine's Setting/Edit. */
    public function boundByLabel(): string
    {
        return $this->creator?->name ?: 'sys';
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
            ->orderByRaw('from_at IS NULL, from_at DESC')
            ->orderByDesc('id')
            ->first(['terminal_id']);

        return $binding?->terminal_id;
    }

    /**
     * The TID on this machine at the instant `$at` — what a sale records as
     * its terminal snapshot. Latest from_at wins where rows overlap (legacy
     * day-precision swaps overlap for the whole swap day).
     */
    public static function terminalIdAt(int $vendId, CarbonInterface $at): ?string
    {
        $binding = static::query()
            ->where('vend_id', $vendId)
            ->effectiveAt($at)
            ->orderByRaw('from_at IS NULL, from_at DESC')
            ->orderByDesc('id')
            ->first(['terminal_id']);

        return $binding?->terminal_id;
    }

    /** Bindings in force at any moment of the given date (null bounds = open-ended). */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        $start = Carbon::parse($date)->startOfDay();

        return $query
            ->where(fn ($q) => $q->whereNull('from_at')->orWhere('from_at', '<', $start->copy()->addDay()))
            ->where(fn ($q) => $q->whereNull('until_at')->orWhere('until_at', '>', $start));
    }

    /** Bindings in force at the instant `$at`: from_at <= at < until_at. */
    public function scopeEffectiveAt(Builder $query, CarbonInterface $at): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('from_at')->orWhere('from_at', '<=', $at))
            ->where(fn ($q) => $q->whereNull('until_at')->orWhere('until_at', '>', $at));
    }
}
