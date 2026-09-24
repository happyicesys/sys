<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementRow;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The leftover pass: pair a NETS line with its machine's TRADE when no time
 * window can (Brian, 2026-09-24). Runs on lines the matcher's windows left
 * unmatched (CardSettlementMatcher::assign, i.e. upload and Rematch) and on
 * NA orphans still awaiting their TRADE (CardSettlementOrphanRepair). Same
 * machine and same cents always; then one of two tiers.
 *
 * Tier A — the machine's learned clock. A board's clock is wrong in a STABLE
 * way: 2760 runs ~314 s slow, 2300's board keeps resetting to 2001 and then
 * ticks correctly from there (2026-09-22/23: board − NETS = −811,226,089 s on
 * three matched sales, ±3 s). The machine's own matched sales around the
 * line's day give those offsets; a sale whose board time sits within the
 * tolerance of one of them is that line's sale, precisely, even when it
 * reached us minutes late. The board time is the frame's raw TIME from the
 * JSON — a frame the 30-day guard rejected is booked at arrival, so that is
 * the only place its stamp survives.
 *
 * Tier B — sequence. With nothing to learn from, a TRADE that reached us
 * between the tap and `match_late_max_lag_seconds` later (poor signal, an
 * outbox flushed after an outage) is paired by order: per machine and
 * amount, lines oldest-first against sales in arrival order, when the two
 * counts agree across the group; otherwise only the pairings unique both
 * ways. Ties never guess — they stay queries.
 *
 * Failed sales pair too (Brian, 2026-09-24): an unclaimed failed TRADE on
 * the same machine with a leftover same-amount line IS the charge for that
 * failed vend — the reconciler then reads it as captured, not voided.
 */
class LateTradePairer
{
    const TIER_CLOCK = 'clock_offset';

    const TIER_SEQUENCE = 'sequence';

    /**
     * @param  Collection<int, CardSettlementRow>  $lines  full-time purchase lines with vend_id set
     * @param  Collection<int, Collection<int, object>>  $salesByVend  vend_id → unclaimed sales (CardSettlementMatcher::candidateColumns())
     * @param  array<int, true>  $taken  sale ids no line may take
     * @return array<int, array{row:CardSettlementRow, sale:object, delta:int, tier:string, note:string}> keyed by row id
     */
    public function pair(Collection $lines, Collection $salesByVend, array $taken = []): array
    {
        $lines = $lines->filter(fn (CardSettlementRow $l) => $l->vend_id && ! $l->time_is_partial && $l->transaction_time !== null)->values();
        if ($lines->isEmpty()) {
            return [];
        }

        $result = $this->pairOnLearnedClock($lines, $salesByVend, $taken);
        foreach ($result as $pair) {
            $taken[$pair['sale']->id] = true;
        }

        $rest = $lines->reject(fn (CardSettlementRow $l) => isset($result[$l->id]))->values();

        return $result + $this->pairInSequence($rest, $salesByVend, $taken);
    }

    /** @return array<int, array> keyed by row id */
    protected function pairOnLearnedClock(Collection $lines, Collection $salesByVend, array $taken): array
    {
        $tolerance = (int) config('card_settlement.match_clock_offset_tolerance_seconds', 60);
        $offsets = $this->learnedOffsets($lines);

        $pairs = [];
        foreach ($lines as $line) {
            $refs = $offsets[$line->vend_id] ?? [];
            if (! $refs) {
                continue;
            }
            $lineAt = self::lineAt($line);
            foreach ($salesByVend->get($line->vend_id) ?? [] as $sale) {
                if (isset($taken[$sale->id]) || (int) $sale->amount !== (int) $line->amount_cents) {
                    continue;
                }
                if (! $this->arrivedAfterTap($lineAt, $sale, (int) config('card_settlement.match_received_anchor_max_lag_seconds', 86400))) {
                    continue;
                }
                $board = self::boardAt($sale);
                if ($board === null) {
                    continue;
                }
                $offset = $board->getTimestamp() - $lineAt->getTimestamp();
                $dev = null;
                foreach ($refs as $ref) {
                    if ($dev === null || abs($offset - $ref) < abs($dev)) {
                        $dev = $offset - $ref;
                    }
                }
                if (abs($dev) <= $tolerance) {
                    $pairs[] = ['row' => $line, 'sale' => $sale, 'delta' => $dev];
                }
            }
        }

        // Closest to a learned offset first, unique both ways.
        usort($pairs, fn ($a, $b) => abs($a['delta']) <=> abs($b['delta']));
        $result = [];
        $claimed = [];
        foreach ($pairs as $p) {
            if (isset($result[$p['row']->id]) || isset($claimed[$p['sale']->id])) {
                continue;
            }
            $result[$p['row']->id] = $p + ['tier' => self::TIER_CLOCK, 'note' => CardSettlementRow::NOTE_MATCHED_CLOCK_OFFSET];
            $claimed[$p['sale']->id] = true;
        }

        return $result;
    }

    /** @return array<int, array> keyed by row id */
    protected function pairInSequence(Collection $lines, Collection $salesByVend, array $taken): array
    {
        $maxLag = (int) config('card_settlement.match_late_max_lag_seconds', 10800);
        if ($maxLag <= 0 || $lines->isEmpty()) {
            return [];
        }

        $result = [];
        foreach ($lines->groupBy(fn (CardSettlementRow $l) => $l->vend_id.'|'.$l->amount_cents) as $group) {
            $first = $group->first();
            $sales = collect($salesByVend->get($first->vend_id) ?? [])
                ->filter(fn ($s) => ! isset($taken[$s->id]) && (int) $s->amount === (int) $first->amount_cents)
                ->values();
            if ($sales->isEmpty()) {
                continue;
            }

            // Eligibility graph: sale reached us inside [tap − early, tap + maxLag],
            // and its own clock, when believable, does not put it hours before the tap.
            $edges = [];
            foreach ($group as $line) {
                $lineAt = self::lineAt($line);
                foreach ($sales as $sale) {
                    if ($this->fitsLate($lineAt, $sale, $maxLag)) {
                        $edges[$line->id][$sale->id] = true;
                    }
                }
            }
            if (! $edges) {
                continue;
            }

            foreach ($this->components($edges) as [$lineIds, $saleIds]) {
                $compLines = $group->whereIn('id', $lineIds)
                    ->sortBy(fn ($l) => self::lineAt($l)->getTimestamp().'.'.str_pad((string) $l->id, 12, '0', STR_PAD_LEFT))
                    ->values();
                $compSales = $sales->whereIn('id', $saleIds)
                    ->sortBy(fn ($s) => sprintf('%012d.%012d.%012d', self::arrivedAt($s)->getTimestamp(), self::boardAt($s)?->getTimestamp() ?? 0, $s->id))
                    ->values();

                $zipped = [];
                if ($compLines->count() === $compSales->count()) {
                    foreach ($compLines as $i => $line) {
                        if (! isset($edges[$line->id][$compSales[$i]->id])) {
                            $zipped = [];
                            break;
                        }
                        $zipped[] = [$line, $compSales[$i]];
                    }
                }
                if (! $zipped) {
                    // Counts disagree (a double tap, a voided sale): only the
                    // pairings nothing else competes for.
                    foreach ($compLines as $line) {
                        $fits = array_keys($edges[$line->id] ?? []);
                        if (count($fits) !== 1) {
                            continue;
                        }
                        $rivals = collect($edges)->filter(fn ($s) => isset($s[$fits[0]]))->count();
                        if ($rivals === 1) {
                            $zipped[] = [$line, $compSales->firstWhere('id', $fits[0])];
                        }
                    }
                }

                foreach ($zipped as [$line, $sale]) {
                    $result[$line->id] = [
                        'row' => $line,
                        'sale' => $sale,
                        'delta' => self::arrivedAt($sale)->getTimestamp() - self::lineAt($line)->getTimestamp(),
                        'tier' => self::TIER_SEQUENCE,
                        'note' => CardSettlementRow::NOTE_MATCHED_LATE_SEQUENCE,
                    ];
                }
            }
        }

        return $result;
    }

    protected function fitsLate(Carbon $lineAt, object $sale, int $maxLag): bool
    {
        $early = (int) config('card_settlement.match_early_slack_seconds', 60);
        $arrivedLag = self::arrivedAt($sale)->getTimestamp() - $lineAt->getTimestamp();
        if ($arrivedLag < -$early || $arrivedLag > $maxLag) {
            return false;
        }
        // Late DELIVERY is what this tier forgives, not a late SALE. A board
        // whose clock is sane must put the sale inside the normal window of
        // the tap (the 2502 burst: +28 s, several same-amount rivals); a
        // drifting sane clock is tier A's job. Only a clock not worth reading
        // (2001, 2069, "14:06:112") leaves arrival order as the evidence.
        // Prod dry run 2026-09-24: 2760's 12:50:41 line — a second charge,
        // its sale claimed by another line — was otherwise paired with a sale
        // its own clock put 23 min later; 2864's with one 10 min later.
        $board = self::boardAt($sale);
        if ($board && self::boardIsBelievable($sale, $board)) {
            $boardLag = $board->getTimestamp() - $lineAt->getTimestamp();

            return $boardLag >= -$early && $boardLag <= (int) config('card_settlement.match_late_slack_seconds', 300);
        }

        return true;
    }

    protected function arrivedAfterTap(Carbon $lineAt, object $sale, int $maxLag): bool
    {
        $early = (int) config('card_settlement.match_early_slack_seconds', 60);
        $lag = self::arrivedAt($sale)->getTimestamp() - $lineAt->getTimestamp();

        return $lag >= -$early && ($maxLag <= 0 || $lag <= $maxLag);
    }

    /**
     * Board-clock offsets (board TIME − NETS time, seconds) per machine, from
     * its real sales already matched within the reference window — normal,
     * receive-anchor and learned-clock matches; never the imprecise wide or
     * sequence pairings.
     *
     * @return array<int, int[]> vend_id → offsets
     */
    protected function learnedOffsets(Collection $lines): array
    {
        $days = (int) config('card_settlement.match_clock_offset_reference_days', 3);
        $from = Carbon::parse($lines->min(fn ($l) => $l->transaction_date->toDateString()))->subDays($days)->toDateString();
        $to = Carbon::parse($lines->max(fn ($l) => $l->transaction_date->toDateString()))->addDays($days)->toDateString();

        $refs = CardSettlementRow::query()
            ->join('vend_transactions as vt', 'vt.id', '=', 'card_settlement_rows.matched_vend_transaction_id')
            ->whereIn('card_settlement_rows.vend_id', $lines->pluck('vend_id')->unique()->values())
            ->whereBetween('card_settlement_rows.transaction_date', [$from, $to])
            ->where('card_settlement_rows.status', CardSettlementRow::STATUS_MATCHED)
            ->where('card_settlement_rows.time_is_partial', false)
            ->whereNotNull('card_settlement_rows.transaction_time')
            ->where('vt.is_found_in_transaction', true)
            ->where(fn ($q) => $q->whereNull('card_settlement_rows.resolution_note')
                ->orWhereNotIn('card_settlement_rows.resolution_note', [
                    CardSettlementRow::NOTE_MATCHED_WIDE,
                    CardSettlementRow::NOTE_MATCHED_LATE_SEQUENCE,
                ]))
            ->get([
                'card_settlement_rows.vend_id',
                'card_settlement_rows.transaction_date',
                'card_settlement_rows.transaction_time',
                'vt.transaction_datetime',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(vt.vend_transaction_json, '$.TIME')) AS frame_time_json"),
            ]);

        $offsets = [];
        foreach ($refs as $ref) {
            $board = self::boardAt($ref);
            if ($board === null) {
                continue;
            }
            $lineAt = Carbon::parse(Carbon::parse($ref->transaction_date)->toDateString().' '.$ref->transaction_time);
            $offsets[$ref->vend_id][] = $board->getTimestamp() - $lineAt->getTimestamp();
        }

        return $offsets;
    }

    /**
     * Connected components of the line↔sale eligibility graph.
     *
     * @param  array<int, array<int, true>>  $edges  line id → sale ids
     * @return array<int, array{0:int[], 1:int[]}>
     */
    protected function components(array $edges): array
    {
        $parent = [];
        $find = function (string $x) use (&$parent, &$find) {
            if (! isset($parent[$x])) {
                $parent[$x] = $x;
            }

            return $parent[$x] === $x ? $x : ($parent[$x] = $find($parent[$x]));
        };
        foreach ($edges as $lineId => $saleIds) {
            foreach (array_keys($saleIds) as $saleId) {
                $parent[$find('l'.$lineId)] = $find('s'.$saleId);
            }
        }

        $groups = [];
        foreach (array_keys($parent) as $node) {
            $root = $find($node);
            $groups[$root][$node[0] === 'l' ? 0 : 1][] = (int) substr($node, 1);
        }

        return array_map(fn ($g) => [$g[0] ?? [], $g[1] ?? []], array_values($groups));
    }

    public static function lineAt(CardSettlementRow $line): Carbon
    {
        return Carbon::parse($line->transaction_date->toDateString().' '.$line->transaction_time);
    }

    /**
     * When mark1 received the sale: the receive anchor where one exists
     * (received_at, or created_at for a legacy TRADE-created row), else
     * transaction_datetime — which IS the arrival for a frame whose TIME the
     * 30-day guard rejected, and for every row before 2026-09-09.
     */
    public static function arrivedAt(object $sale): Carbon
    {
        return CardSettlementMatcher::receivedAnchor($sale) ?? Carbon::parse($sale->transaction_datetime);
    }

    /**
     * The board's own stamp: the raw frame TIME, else transaction_datetime.
     * A TIME that is present but unparseable ("2026-08-03 14:06:112", 2624)
     * means no readable clock — null, never transaction_datetime, which for
     * such a frame is only the arrival and would pass for a sane clock.
     */
    public static function boardAt(object $sale): ?Carbon
    {
        $raw = $sale->frame_time_json ?? null;
        if (is_string($raw) && $raw !== '' && $raw !== 'null') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return empty($sale->transaction_datetime) ? null : Carbon::parse($sale->transaction_datetime);
    }

    /** A board stamp within a day of arrival is a clock worth reading; a 2001 one is not. */
    protected static function boardIsBelievable(object $sale, Carbon $board): bool
    {
        return abs($board->getTimestamp() - self::arrivedAt($sale)->getTimestamp()) <= 86400;
    }
}
