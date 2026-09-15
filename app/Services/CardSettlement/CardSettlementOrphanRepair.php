<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementRow;
use App\Models\VendTransaction;
use App\Services\Sales\DirtyDayRegistry;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Undo the orphans the single-anchor matcher manufactured.
 *
 * Until 2026-09-15 a NETS line was matched on ONE sale time. That missed the
 * real sale whenever the board's clock (frame TIME, the anchor since 09-09)
 * was minutes off — 2760 at −314 s produced 89 orphans in four days — or,
 * in the server-time era before that, whenever an offline machine flushed
 * its outbox in a burst (2502, 2026-09-03). Sync then CREATED a code-99 sale
 * from each such line (CardSettlementOrphanSales), so the machine's own sale
 * and the report's copy both sit in revenue, the real sale reads
 * `not_captured`, and a failed single among them is ticked "NA in NETS".
 *
 * This walks every orphan still awaiting its TRADE and asks the matcher's
 * own question with BOTH anchors (CardSettlementMatcher::timeDeltaDetail,
 * normal window only — never the wide pass, this is bulk): an unclaimed
 * card sale on the same machine, same cents, inside the window on frame
 * time or receive time. Pairs are assigned greedily, closest to the expected
 * lag first, unique both ways — so the 2502 shape (two orphans, two real
 * sales, all $4.60) resolves pairwise instead of guessing.
 *
 * A repair, in one transaction: release the line's claim, delete the orphan
 * (+ items), re-point the line at the real sale with the
 * NOTE_REPAIRED_FROM_ORPHAN note, carry the Sync stamp over, dirty both
 * days. The caller then re-runs CardSettlementRefundReconciler on the
 * affected days: the real sale becomes `captured` (its owned tick cleared)
 * or `reversed` when the line is, and the deleted orphan's revenue leaves
 * with the day's rollup rebuild.
 *
 * Idempotent: a repaired line no longer has an orphan, so a second run finds
 * nothing for it.
 */
class CardSettlementOrphanRepair
{
    public function __construct(
        protected CardSettlementMatcher $matcher,
        protected DirtyDayRegistry $dirtyDays,
    ) {}

    /**
     * Orphans dated inside [$from, $until] (optionally on one machine) with
     * the real sale each would be repaired to, or null when none fits uniquely.
     *
     * @return Collection<int, array{orphan:VendTransaction,row:CardSettlementRow,sale:?object,delta:?int,anchor:?string,reason:?string}>
     */
    public function plan(CarbonInterface $from, CarbonInterface $until, ?int $vendId = null): Collection
    {
        $orphans = VendTransaction::query()
            ->withoutGlobalScopes()
            ->whereNotNull('card_settlement_row_id')
            ->where('is_found_in_transaction', false)
            ->whereBetween('transaction_datetime', [$from, $until])
            ->when($vendId, fn ($q) => $q->where('vend_id', $vendId))
            ->orderBy('vend_id')
            ->orderBy('transaction_datetime')
            ->get();
        if ($orphans->isEmpty()) {
            return collect();
        }

        $rows = CardSettlementRow::query()
            ->whereIn('id', $orphans->pluck('card_settlement_row_id'))
            ->get()
            ->keyBy('id');

        $earlySlack = (int) config('card_settlement.match_early_slack_seconds', 60);
        $lateSlack = (int) config('card_settlement.match_late_slack_seconds', 300);

        // Candidate sales: same population the matcher uses, per machine,
        // around the orphans' own span (an orphan is dated at its line time).
        $candidatesByVend = $this->matcher->unclaimedCandidates(
            $orphans->pluck('vend_id')->unique()->values()->all(),
            Carbon::parse($orphans->min('transaction_datetime'))->subDay(),
            Carbon::parse($orphans->max('transaction_datetime'))->addDay(),
        );

        $plan = [];
        $pairs = [];
        foreach ($orphans as $orphan) {
            $row = $rows->get($orphan->card_settlement_row_id);
            $entry = ['orphan' => $orphan, 'row' => $row, 'sale' => null, 'delta' => null, 'anchor' => null, 'reason' => null];

            // The line must still own this orphan; anything else was
            // re-pointed by a human (Assign) and is not ours to touch.
            if (! $row || (int) $row->matched_vend_transaction_id !== (int) $orphan->id) {
                $entry['reason'] = 'line no longer points at this orphan';
                $plan[$orphan->id] = $entry;

                continue;
            }
            if ($row->time_is_partial || $row->transaction_time === null) {
                $entry['reason'] = 'line has no full timestamp';
                $plan[$orphan->id] = $entry;

                continue;
            }

            $fits = 0;
            foreach ($candidatesByVend->get($orphan->vend_id) ?? [] as $sale) {
                if ((int) $sale->amount !== (int) $orphan->amount) {
                    continue;
                }
                $fit = $this->matcher->timeDeltaDetail($row, $sale, $earlySlack, $lateSlack);
                if ($fit === null) {
                    continue;
                }
                $fits++;
                $pairs[] = ['orphan_id' => $orphan->id, 'sale' => $sale, 'delta' => $fit['delta'], 'anchor' => $fit['anchor']];
            }
            $entry['reason'] = $fits === 0 ? 'no sale fits on either anchor' : null;
            $plan[$orphan->id] = $entry;
        }

        // Greedy, closest to the expected lag first, unique both ways.
        usort($pairs, fn ($a, $b) => abs($a['delta'] - CardSettlementMatcher::EXPECTED_LAG_SECONDS) <=> abs($b['delta'] - CardSettlementMatcher::EXPECTED_LAG_SECONDS));
        $takenSales = [];
        foreach ($pairs as $pair) {
            $entry = &$plan[$pair['orphan_id']];
            if ($entry['sale'] !== null || isset($takenSales[$pair['sale']->id])) {
                continue;
            }
            $entry['sale'] = $pair['sale'];
            $entry['delta'] = $pair['delta'];
            $entry['anchor'] = $pair['anchor'];
            $entry['reason'] = null;
            $takenSales[$pair['sale']->id] = true;
            unset($entry);
        }
        foreach ($plan as &$entry) {
            if ($entry['sale'] === null && $entry['reason'] === null) {
                $entry['reason'] = 'every fitting sale was taken by another orphan';
            }
        }
        unset($entry);

        return collect(array_values($plan));
    }

    /**
     * Apply one planned repair. Returns the calendar days whose rollups and
     * reconciler state must be re-run.
     *
     * @param  array{orphan:VendTransaction,row:CardSettlementRow,sale:object,delta:int,anchor:string}  $entry
     * @return string[] Y-m-d
     */
    public function apply(array $entry): array
    {
        $orphan = $entry['orphan'];
        $row = $entry['row'];
        $sale = $entry['sale'];

        return DB::transaction(function () use ($orphan, $row, $sale, $entry) {
            // Re-read under lock: the TRADE may have adopted the orphan, or a
            // human re-pointed the line, since the plan was built.
            $orphan = VendTransaction::withoutGlobalScopes()->whereKey($orphan->id)->lockForUpdate()->first();
            $row = CardSettlementRow::whereKey($row->id)->lockForUpdate()->first();
            if (! $orphan || ! $row || $orphan->is_found_in_transaction || (int) $row->matched_vend_transaction_id !== (int) $orphan->id) {
                return [];
            }
            $realClaimed = CardSettlementRow::where('matched_vend_transaction_id', $sale->id)->exists();
            if ($realClaimed) {
                return [];
            }

            $orphanDay = Carbon::parse($orphan->transaction_datetime)->toDateString();
            $saleDay = Carbon::parse($sale->transaction_datetime)->toDateString();
            $syncedAt = $orphan->card_settlement_synced_at;

            // Release the UNIQUE claim first, then the orphan goes.
            $row->forceFill(['matched_vend_transaction_id' => null])->save();
            $orphan->vendTransactionItems()->delete();
            $orphan->delete();

            $row->forceFill([
                'status' => CardSettlementRow::STATUS_MATCHED,
                'matched_vend_transaction_id' => $sale->id,
                'match_time_delta' => $entry['delta'],
                'candidates_json' => null,
                'resolution_note' => CardSettlementRow::NOTE_REPAIRED_FROM_ORPHAN,
            ])->save();

            // The line was already Synced onto the orphan; the real sale
            // inherits that stamp (not fillable — query update, as Sync does).
            if ($syncedAt) {
                VendTransaction::withoutGlobalScopes()->whereKey($sale->id)
                    ->whereNull('card_settlement_synced_at')
                    ->update(['card_settlement_synced_at' => $syncedAt]);
            }

            $this->dirtyDays->mark($orphanDay);
            $this->dirtyDays->mark($saleDay);

            Log::info('CardSettlementOrphanRepair: orphan replaced by real sale', [
                'row_id' => $row->id, 'orphan_id' => $orphan->id, 'sale_id' => $sale->id,
                'delta' => $entry['delta'], 'anchor' => $entry['anchor'],
            ]);

            return array_values(array_unique([$orphanDay, $saleDay]));
        });
    }
}
