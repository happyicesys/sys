<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalUnit;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\Sales\DirtyDayRegistry;
use App\Services\Sales\PreCreatedSaleFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NETS line, no TRADE (NA_ERROR_CODE_PLAN_2026-09-08.md Part 2, direction 1).
 *
 * A purchase line the matcher left `UNMATCHED / "No matching sale in window"`
 * on a BOUND terminal is money NETS took for a sale mark1 never heard about.
 * At Sync — the operator's "this report is settled" step, after Rematch /
 * Assign / Ignore / binding fixes have had their say — each such line becomes
 * a real vend_transactions row (PreCreatedSaleFactory::fromSettlementLine:
 * code 99, Dispense blank, no product, settled or refunded per the line) and
 * the line flips to MATCHED pointing at it. Not created: double taps ("All
 * matching sales already claimed" — a human decides), wrong-machine lines
 * (binding fix), unbound TIDs (no vend), hour-less lines (Part 4 item 4: an
 * orphan dated 00:mm:ss could never be adopted).
 *
 * Idempotent by construction: the line's UNIQUE claim on the sale means a
 * Rematch skips it, a re-uploaded file is DUPLICATE by fingerprint, and a
 * second Sync finds no qualifying line.
 *
 * release(): Assign / Ignore on a line whose sale is an orphan STILL awaiting
 * its TRADE deletes that orphan (and dirties its day), so moving a line to
 * the real sale can never leave two rows. An adopted orphan (TRADE arrived)
 * is a real sale and is never deleted here.
 */
class CardSettlementOrphanSales
{
    public function __construct(
        protected PreCreatedSaleFactory $factory,
        protected DirtyDayRegistry $dirtyDays,
    ) {}

    /** Lines of this report that qualify for an orphan sale. */
    public function candidates(CardSettlementReport $report)
    {
        return $report->rows()
            ->saleLines()
            ->where('status', CardSettlementRow::STATUS_UNMATCHED)
            ->where('is_reversal', false)
            ->where('resolution_note', CardSettlementRow::NOTE_NO_SALE_IN_WINDOW)
            ->whereNotNull('vend_id')
            ->where('time_is_partial', false)
            ->whereNotNull('transaction_time')
            ->orderBy('row_no');
    }

    /** @return int sales created */
    public function createForReport(CardSettlementReport $report): int
    {
        $rows = $this->candidates($report)->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $vends = Vend::withoutGlobalScopes()
            ->with(['customer.locationType', 'customer.operator', 'operator', 'vendContract', 'vendModel', 'vendPrefix'])
            ->whereIn('id', $rows->pluck('vend_id')->unique())
            ->get()
            ->keyBy('id');
        $units = CardTerminalUnit::query()
            ->with('company')
            ->whereIn('terminal_id', $rows->pluck('terminal_id')->unique())
            ->get()
            ->keyBy('terminal_id');
        $cardMethodId = PaymentMethod::query()->where('code', PaymentMethod::CODE_CARD_TERMINAL)->value('id');

        $created = 0;
        foreach ($rows as $row) {
            $vend = $vends->get($row->vend_id);
            if (! $vend) {
                continue;
            }
            $mfg = $units->get($row->terminal_id)?->company?->name ?? 'Nets';

            try {
                DB::transaction(function () use ($row, $vend, $cardMethodId, $mfg) {
                    $sale = $this->factory->fromSettlementLine($row, $vend, $cardMethodId, $mfg);
                    $row->update([
                        'status' => CardSettlementRow::STATUS_MATCHED,
                        'matched_vend_transaction_id' => $sale->id,
                        'match_time_delta' => null,
                        'candidates_json' => null,
                        'resolution_note' => CardSettlementRow::NOTE_CREATED_FROM_REPORT,
                    ]);
                });
            } catch (QueryException $e) {
                // UNIQUE claim or order id already taken: a concurrent Sync /
                // Rematch got here first. Nothing to do for this line.
                Log::warning('CardSettlementOrphanSales: line skipped', ['row_id' => $row->id, 'error' => $e->getMessage()]);

                continue;
            }

            // Reports are always ≥ 1 day late, so every orphan is a past-day insert.
            $this->dirtyDays->mark($row->transaction_date);
            $created++;
        }

        if ($created) {
            $report->refreshCounts();
        }

        return $created;
    }

    /**
     * Before a line is re-pointed (Assign) or dismissed (Ignore): if its sale
     * is an orphan still awaiting its TRADE, delete that orphan. Returns
     * whether one was deleted.
     */
    public function release(CardSettlementRow $row): bool
    {
        if (! $row->matched_vend_transaction_id) {
            return false;
        }

        $sale = VendTransaction::withoutGlobalScopes()
            ->whereKey($row->matched_vend_transaction_id)
            ->where('card_settlement_row_id', $row->id)
            ->where('is_found_in_transaction', false)
            ->first();
        if (! $sale) {
            return false;
        }

        $day = $sale->transaction_datetime;
        // Release the claim first: the UNIQUE index on matched_vend_transaction_id
        // is what stops two lines owning one sale.
        $row->forceFill(['matched_vend_transaction_id' => null])->save();
        $sale->vendTransactionItems()->delete();
        $sale->delete();
        $this->dirtyDays->mark($day);

        Log::info('CardSettlementOrphanSales: orphan sale deleted', ['vend_transaction_id' => $sale->id, 'row_id' => $row->id]);

        return true;
    }
}
