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
}
