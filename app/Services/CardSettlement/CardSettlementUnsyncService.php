<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\VendTransaction;
use App\Services\Sales\DirtyDayRegistry;
use App\Services\Sales\RollupRebuilder;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * "Undo sync" and Delete on a NETS report (Brian, 2026-10-05: a synced report
 * could never be removed, so a wrong or partial file stayed on the books).
 *
 * unsync() puts a synced report back in review and takes back what its Sync
 * wrote, so the sales read as if it had never been synced:
 *
 *  1. orphan sales it created that are still awaiting their TRADE are deleted
 *     and their lines go back to "No matching sale in window" (a re-Sync
 *     creates them again); an orphan a TRADE has adopted is a real sale and
 *     stays;
 *  2. the card_settlement_synced_at stamp comes off every sale it matched;
 *  3. the report flips to review — from here on it no longer counts towards
 *     "day final", so its two days stop being final;
 *  4. retained-credit re-vend links it proved on those days are removed;
 *  5. every day it touched is unwound (CardSettlementRefundReconciler::unwindDay):
 *     reversal and "NA in NETS" ticks it no longer supports are cleared and
 *     their tickets released, its persisted states go back to NULL, and the
 *     day is reconciled again from the reports that remain;
 *  6. those days' rollups are rebuilt.
 *
 * Line matching is NOT undone: it is the matcher's view of the file, Rematch
 * redoes it, and a terminal moved on this report's evidence stays moved.
 *
 * forgetDeleted() is the delete-side half: once a report's rows are gone, the
 * sales its lines vouched for (including purchase lines in OTHER reports that
 * its reversal lines undid) are unwound the same way.
 */
class CardSettlementUnsyncService
{
    const CHUNK = 500;

    public function __construct(
        protected CardSettlementOrphanSales $orphans,
        protected CardSettlementRefundReconciler $reconciler,
        protected RetainedCreditLinker $retainedCredit,
        protected RollupRebuilder $rollups,
        protected DirtyDayRegistry $dirtyDays,
    ) {}

    /**
     * Holds the report ({@see CardSettlementReport::exclusively}) and re-checks it is still
     * synced under the lock, so two clicks, or an Undo Sync racing a Sync, never both act.
     * The days to unwind are recorded on the report in the same transaction that flips it
     * to review (`unwind_pending_json`) and cleared once the unwind finishes: a failure
     * part-way leaves that marker for {@see finishPendingUnwind()} instead of stale ticks
     * nobody knows about.
     *
     * @return array{orphans_deleted:int, unstamped:int, links_removed:int, days:array, ticks_cleared:int, tickets_released:int}
     *
     * @throws DomainException when the report is not synced
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException when another action holds the report
     */
    public function unsync(CardSettlementReport $report, ?int $userId): array
    {
        return $report->exclusively(fn (CardSettlementReport $fresh) => $this->unsyncLocked($fresh, $userId));
    }

    /** Caller holds the report lock. */
    protected function unsyncLocked(CardSettlementReport $report, ?int $userId): array
    {
        if ($report->status !== CardSettlementReport::STATUS_SYNCED) {
            throw new DomainException('Only a synced report can be un-synced.');
        }

        $days = $this->coveredDays($report);
        $orphansDeleted = 0;
        $unstamped = 0;

        DB::transaction(function () use ($report, &$days, &$orphansDeleted, &$unstamped) {
            $rows = $report->rows()
                ->where('status', CardSettlementRow::STATUS_MATCHED)
                ->whereNotNull('matched_vend_transaction_id')
                ->get();
            $sales = VendTransaction::withoutGlobalScopes()
                ->whereIn('id', $rows->pluck('matched_vend_transaction_id'))
                ->get(['id', 'transaction_datetime', 'card_settlement_row_id', 'is_found_in_transaction'])
                ->keyBy('id');

            foreach ($sales as $sale) {
                $days[] = Carbon::parse($sale->transaction_datetime)->toDateString();
            }

            $keep = [];
            foreach ($rows as $row) {
                $sale = $sales->get($row->matched_vend_transaction_id);
                if ($sale && (int) $sale->card_settlement_row_id === $row->id && ! $sale->is_found_in_transaction) {
                    if ($this->orphans->release($row)) {
                        $row->forceFill([
                            'status' => CardSettlementRow::STATUS_UNMATCHED,
                            'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW,
                        ])->save();
                        $orphansDeleted++;

                        continue;
                    }
                }
                $keep[] = $row->matched_vend_transaction_id;
            }

            foreach (array_chunk($keep, self::CHUNK) as $chunk) {
                $unstamped += VendTransaction::withoutGlobalScopes()
                    ->whereIn('id', $chunk)
                    ->whereNotNull('card_settlement_synced_at')
                    ->update(['card_settlement_synced_at' => null]);
            }

            $report->forceFill([
                'status' => CardSettlementReport::STATUS_REVIEW,
                'synced_count' => 0,
                'refunded_count' => 0,
                'synced_at' => null,
                'synced_by' => null,
                'unwind_pending_json' => [
                    'days' => array_values(array_unique($days)),
                    'revend_days' => $this->coveredDays($report),
                    'since' => now()->toIso8601String(),
                ],
            ])->save();
            $report->refreshCounts();
        });

        $result = $this->runPendingUnwind($report);

        Log::info('Card settlement report un-synced', [
            'report_id' => $report->id,
            'file' => $report->original_filename,
            'user_id' => $userId,
            'orphans_deleted' => $orphansDeleted,
            'unstamped' => $unstamped,
        ] + $result);

        return ['orphans_deleted' => $orphansDeleted, 'unstamped' => $unstamped] + $result;
    }

    /**
     * Re-runs an Undo Sync's unwind that did not finish (`card-settlement:finish-unwind`).
     * unwindDay is idempotent: it takes back only what the synced reports no longer
     * support, then reconciles the day again.
     */
    public function finishPendingUnwind(CardSettlementReport $report): array
    {
        return $report->exclusively(fn (CardSettlementReport $fresh) => $fresh->unwind_pending_json === null
            ? ['links_removed' => 0, 'days' => [], 'ticks_cleared' => 0, 'tickets_released' => 0]
            : $this->runPendingUnwind($fresh));
    }

    /** Unwinds the report's recorded days, then clears the marker. Caller holds the report lock. */
    protected function runPendingUnwind(CardSettlementReport $report): array
    {
        $pending = (array) $report->unwind_pending_json;
        $result = $this->unwind((array) ($pending['days'] ?? []), (array) ($pending['revend_days'] ?? []), []);
        $report->forceFill(['unwind_pending_json' => null])->save();

        return $result;
    }

    /**
     * Delete a report that is not synced (Undo Sync first). Held like Sync / Undo Sync, the
     * status re-checked under the lock. The DB rows go in one transaction and the stored
     * file only after it commits — a failed delete never leaves rows pointing at a missing
     * file. What the report vouched for outside its own rows is then unwound
     * ({@see forgetDeleted}); if that fails the report is already gone, so the exact
     * command to finish it is logged and returned.
     *
     * @return array{links_removed:int, days:array, ticks_cleared:int, tickets_released:int, unwind_failed?:string}
     *
     * @throws DomainException when the report is synced or still matching
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException when another action holds the report
     */
    public function delete(CardSettlementReport $report): array
    {
        return $report->exclusively(function (CardSettlementReport $report) {
            if ($report->status === CardSettlementReport::STATUS_MATCHING) {
                throw new DomainException('Matching is still running.');
            }
            if ($report->status === CardSettlementReport::STATUS_SYNCED) {
                throw new DomainException('A synced report cannot be deleted — Undo sync first.');
            }

            $evidence = $this->evidenceOf($report);
            $disk = $report->fileDisk();
            $files = DB::transaction(function () use ($report) {
                $files = [];
                foreach ($report->attachments as $attachment) {
                    if ($attachment->local_url) {
                        $files[] = $attachment->local_url;
                    }
                    $attachment->delete();
                }
                $report->delete(); // rows cascade

                return $files;
            });

            foreach ($files as $path) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable $e) {
                    Log::warning('Card settlement report file not deleted', ['report_id' => $report->id, 'path' => $path, 'error' => $e->getMessage()]);
                }
            }

            try {
                return $this->forgetDeleted($evidence);
            } catch (Throwable $e) {
                report($e);
                $command = 'php artisan card-settlement:finish-unwind --days='.implode(',', array_unique($evidence['days']))
                    .($evidence['sale_ids'] ? ' --top-up-sales='.implode(',', $evidence['sale_ids']) : '');
                Log::error('Card settlement report deleted but its unwind failed — finish it by hand', [
                    'report_id' => $report->id, 'command' => $command, 'error' => $e->getMessage(),
                ]);

                return ['links_removed' => 0, 'days' => [], 'ticks_cleared' => 0, 'tickets_released' => 0, 'unwind_failed' => $command];
            }
        });
    }

    /**
     * The delete-side unwind for days given by hand (the command logged by a failed
     * {@see delete()}).
     *
     * @param  string[]  $days
     * @param  int[]  $topUpSaleIds
     */
    public function unwindDays(array $days, array $topUpSaleIds = []): array
    {
        return $this->forgetDeleted(['days' => $days, 'sale_ids' => $topUpSaleIds]);
    }

    /**
     * Before a (not synced) report is deleted: what it vouches for outside its
     * own rows. Call {@see forgetDeleted()} with the result once it is gone.
     *
     * @return array{days:array, sale_ids:array}
     */
    public function evidenceOf(CardSettlementReport $report): array
    {
        $rowIds = $report->rows()->pluck('id');

        // Purchase lines in other reports that this report's reversal lines undo.
        $reversedElsewhere = CardSettlementRow::query()
            ->whereIn('reversed_by_row_id', $rowIds)
            ->where('card_settlement_report_id', '!=', $report->id)
            ->whereNotNull('matched_vend_transaction_id')
            ->pluck('matched_vend_transaction_id');
        $own = $report->rows()->whereNotNull('matched_vend_transaction_id')->pluck('matched_vend_transaction_id');

        $days = VendTransaction::withoutGlobalScopes()
            ->whereIn('id', $reversedElsewhere->merge($own)->unique())
            ->pluck('transaction_datetime')
            ->map(fn ($at) => Carbon::parse($at)->toDateString())
            ->all();

        return [
            'days' => array_merge($this->coveredDays($report), $days),
            'sale_ids' => $own->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ];
    }

    /** After the delete: unwind the days and links the report's lines vouched for. */
    public function forgetDeleted(array $evidence): array
    {
        // The line an orphan was created from is gone; an adopted orphan is a real sale.
        foreach (array_chunk($evidence['sale_ids'], self::CHUNK) as $chunk) {
            VendTransaction::withoutGlobalScopes()->whereIn('id', $chunk)->whereNotNull('card_settlement_row_id')
                ->update(['card_settlement_row_id' => null]);
        }

        // An unsynced report finalised no day, so it proved no re-vend; only its top-up lines go.
        return $this->unwind($evidence['days'], [], $evidence['sale_ids']);
    }

    /** @return string[] the report's cutover day and the day before */
    protected function coveredDays(CardSettlementReport $report): array
    {
        return array_map(fn ($d) => $d->toDateString(), CardSettlementRefundReconciler::daysCoveredBy($report));
    }

    /**
     * @param  string[]  $days  every day whose sales the report vouched for
     * @param  string[]  $revendDays  days that just stopped being final (re-vend links)
     * @param  int[]  $topUpSaleIds  sales whose top-up line is going away
     */
    protected function unwind(array $days, array $revendDays, array $topUpSaleIds): array
    {
        $days = array_values(array_unique(array_filter($days)));
        sort($days);
        sort($revendDays);

        $linksRemoved = $this->retainedCredit->unlinkWithdrawn(
            $revendDays ? Carbon::parse($revendDays[0])->startOfDay() : null,
            $revendDays ? Carbon::parse(end($revendDays))->endOfDay() : null,
            fn ($day) => $this->reconciler->isDayFinal($day),
            $topUpSaleIds
        );

        $ticks = 0;
        $tickets = 0;
        foreach ($days as $day) {
            $stats = $this->reconciler->unwindDay(Carbon::parse($day));
            $ticks += $stats['ticks_cleared'];
            $tickets += $stats['tickets_released'];
            $this->dirtyDays->mark($day);
        }
        $this->rollups->dispatchDays($days);

        return ['links_removed' => $linksRemoved, 'days' => $days, 'ticks_cleared' => $ticks, 'tickets_released' => $tickets];
    }
}
