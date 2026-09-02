<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\PaymentMethod;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CardSettlementSyncService — the "Sync" button stamps
 * card_settlement_synced_at onto every sale a report's matched rows claim.
 * Idempotent: an earlier stamp survives a re-sync.
 */
class CardSettlementSyncTest extends TestCase
{
    use RefreshDatabase;

    private function txn(int $n): VendTransaction
    {
        $card = PaymentMethod::firstOrCreate(['code' => 1], ['name' => 'Card Terminal', 'is_active' => true]);

        return VendTransaction::create([
            'order_id' => 'ORD-SYNC-'.$n,
            'vend_id' => 1320,
            'transaction_datetime' => '2026-08-29 22:31:07',
            'amount' => 240,
            'qty' => 1,
            'success_qty' => 1,
            'dispensed_qty' => 1,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_method_id' => $card->id,
            'cashless_mfg' => 'Nets',
        ]);
    }

    public function test_sync_stamps_matched_sales_and_closes_the_report()
    {
        $report = CardSettlementReport::create([
            'provider' => 'nets',
            'original_filename' => 'test.csv',
            'status' => CardSettlementReport::STATUS_REVIEW,
        ]);

        $matched = $this->txn(1);
        $unmatchedTxn = $this->txn(2);

        CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => 1,
            'txn_type' => 'Purchase',
            'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29',
            'amount_cents' => 240,
            'fingerprint' => sha1('sync-1'),
            'status' => CardSettlementRow::STATUS_MATCHED,
            'matched_vend_transaction_id' => $matched->id,
        ]);
        CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => 2,
            'txn_type' => 'Purchase',
            'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29',
            'amount_cents' => 240,
            'fingerprint' => sha1('sync-2'),
            'status' => CardSettlementRow::STATUS_UNMATCHED,
        ]);

        $count = app(CardSettlementSyncService::class)->sync($report, null);

        $this->assertSame(1, $count);
        $this->assertNotNull($matched->fresh()->card_settlement_synced_at);
        $this->assertNull($unmatchedTxn->fresh()->card_settlement_synced_at);

        $report->refresh();
        $this->assertSame(CardSettlementReport::STATUS_SYNCED, $report->status);
        $this->assertSame(1, $report->synced_count);
        $this->assertNotNull($report->synced_at);

        // Re-sync keeps the original stamp.
        $stamp = $matched->fresh()->card_settlement_synced_at;
        $this->travel(1)->minutes();
        app(CardSettlementSyncService::class)->sync($report, null);
        $this->assertEquals($stamp, $matched->fresh()->card_settlement_synced_at);
    }

    public function test_sync_marks_a_reversed_sale_refunded_from_the_report()
    {
        $report = CardSettlementReport::create([
            'provider' => 'nets',
            'original_filename' => 'test.csv',
            'status' => CardSettlementReport::STATUS_REVIEW,
        ]);

        $reversed = $this->txn(1);
        $alreadyRefunded = $this->txn(2);
        $alreadyRefunded->forceFill([
            'is_refunded' => true,
            'auto_refund_source' => \App\Support\AutoRefundSource::CARD_TERMINAL_REVERSAL,
        ])->save();
        $untouched = $this->txn(3);

        $purchase = CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => 1,
            'txn_type' => 'Purchase',
            'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29',
            'amount_cents' => 240,
            'fingerprint' => sha1('rev-1'),
            'status' => CardSettlementRow::STATUS_MATCHED,
            'matched_vend_transaction_id' => $reversed->id,
        ]);
        $reversal = CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => 2,
            'txn_type' => 'Purchase',
            'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29',
            'amount_cents' => -240,
            'is_reversal' => true,
            'fingerprint' => sha1('rev-2'),
            'status' => CardSettlementRow::STATUS_MATCHED,
            'reverses_row_id' => $purchase->id,
        ]);
        $purchase->update(['reversed_by_row_id' => $reversal->id]);

        // Heuristic-refunded sale that the report ALSO reversed: left as-is.
        $p2 = CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => 3,
            'txn_type' => 'Purchase',
            'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29',
            'amount_cents' => 240,
            'fingerprint' => sha1('rev-3'),
            'status' => CardSettlementRow::STATUS_MATCHED,
            'matched_vend_transaction_id' => $alreadyRefunded->id,
            'reversed_by_row_id' => 999999,
        ]);
        CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => 4,
            'txn_type' => 'Purchase',
            'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29',
            'amount_cents' => 240,
            'fingerprint' => sha1('rev-4'),
            'status' => CardSettlementRow::STATUS_MATCHED,
            'matched_vend_transaction_id' => $untouched->id,
        ]);

        app(CardSettlementSyncService::class)->sync($report, null);

        $reversed->refresh();
        $this->assertTrue((bool) $reversed->is_refunded);
        $this->assertSame(\App\Support\AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, $reversed->auto_refund_source);
        $this->assertNotNull($reversed->card_settlement_synced_at);

        $this->assertSame(\App\Support\AutoRefundSource::CARD_TERMINAL_REVERSAL, $alreadyRefunded->fresh()->auto_refund_source);
        $this->assertFalse((bool) $untouched->fresh()->is_refunded);
        $this->assertSame(2, $report->fresh()->refunded_count);
    }

    /**
     * Purchase at 22:29 lands in report A (already synced), the reader's
     * reversal at 22:31 lands in the next day's report B. Syncing B must reach
     * the purchase line in A and mark its sale refunded — and a later re-sync
     * of A must not touch it again.
     */
    public function test_sync_marks_a_sale_whose_purchase_line_sits_in_an_earlier_report()
    {
        $reportA = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'a.csv', 'status' => CardSettlementReport::STATUS_SYNCED]);
        $reportB = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'b.csv', 'status' => CardSettlementReport::STATUS_REVIEW]);
        $sale = $this->txn(1);

        $purchase = CardSettlementRow::create([
            'card_settlement_report_id' => $reportA->id, 'row_no' => 1, 'txn_type' => 'Purchase', 'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29', 'amount_cents' => 240, 'fingerprint' => sha1('x-1'),
            'status' => CardSettlementRow::STATUS_MATCHED, 'matched_vend_transaction_id' => $sale->id,
        ]);
        $reversal = CardSettlementRow::create([
            'card_settlement_report_id' => $reportB->id, 'row_no' => 1, 'txn_type' => 'Purchase', 'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29', 'amount_cents' => -240, 'is_reversal' => true, 'fingerprint' => sha1('x-2'),
            'status' => CardSettlementRow::STATUS_MATCHED, 'reverses_row_id' => $purchase->id,
        ]);
        $purchase->update(['reversed_by_row_id' => $reversal->id]);

        app(CardSettlementSyncService::class)->sync($reportB, null);

        $sale->refresh();
        $this->assertTrue((bool) $sale->is_refunded);
        $this->assertSame(\App\Support\AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, $sale->auto_refund_source);
        $this->assertSame(1, $reportB->fresh()->refunded_count);
        $this->assertSame(0, $reportB->fresh()->synced_count); // the reversal line claims no sale

        // Re-syncing the purchase's own report is idempotent.
        $reportA->update(['status' => CardSettlementReport::STATUS_REVIEW]);
        app(CardSettlementSyncService::class)->sync($reportA, null);
        $this->assertSame(1, $reportA->fresh()->refunded_count);
        $this->assertSame(\App\Support\AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, $sale->fresh()->auto_refund_source);
    }
}
