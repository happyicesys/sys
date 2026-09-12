<?php

namespace App\Support\CardSettlement;

use Carbon\CarbonImmutable;

/**
 * One settlement report line's payout: the banking day its money reaches the
 * bank, the gateway it comes through, and the schedule that produced both.
 *
 * Derived on read from the line's own transaction date — never stored. The
 * report line is the fact; this is an arithmetic consequence of it, so a
 * corrected rule or a newly seeded public holiday fixes every historical row
 * at once instead of needing a backfill.
 */
class SettlementPayout
{
    public function __construct(
        /** The banking day the money lands. */
        public readonly CarbonImmutable $date,
        /** The report line's own transaction date, which T+N counts from. */
        public readonly CarbonImmutable $transactionDate,
        public readonly PayoutTerms $terms,
        /** Card family as the report spelled it ("EFTPOS / DBS PayLah"), for the tooltip. */
        public readonly ?string $scheme = null,
    ) {}

    public function gateway(): string
    {
        return $this->terms->gateway;
    }

    /** The grid prints dates as ymd; keep the cell consistent with its neighbours. */
    public function shortDate(): string
    {
        return $this->date->format('ymd');
    }

    /**
     * Hover text — the whole derivation in one line, so a disputed date can be
     * argued from the cell alone:
     * "EFTPOS / DBS PayLah — COS, T+1 banking day from 2026-09-01, MDR 0.8% (full back in)"
     */
    public function describe(): string
    {
        $parts = [];

        if ($this->scheme !== null) {
            $parts[] = $this->scheme.' — '.$this->terms->gateway;
        } else {
            $parts[] = $this->terms->gateway;
        }

        $parts[] = $this->terms->termLabel()
            .' banking '.($this->terms->termDays === 1 ? 'day' : 'days')
            .' from '.$this->transactionDate->toDateString();

        if ($this->terms->mdrRate !== null) {
            $parts[] = 'MDR '.$this->terms->mdrRate
                .($this->terms->mdrNote !== null ? ' ('.$this->terms->mdrNote.')' : '');
        }

        return implode(', ', $parts);
    }
}
