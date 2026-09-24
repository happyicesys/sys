<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Sales Transactions "Settle Sync?" filter (request key `settle_sync`).
 *
 * It must list exactly what the Settle Sync column ticks / crosses:
 * card terminals by card_settlement_synced_at, gateways by the linked
 * payment_gateway_log's approve (2) or approved-then-refunded (98) status.
 * Cash has no rail to confirm it — the cell is blank — so it is on neither side.
 */
class TransactionIndexSettleSyncFilterTest extends TestCase
{
    use RefreshDatabase;

    private function txn(array $attrs): VendTransaction
    {
        static $n = 0;

        // forceFill: card_settlement_synced_at is not mass-assignable (only
        // CardSettlementSyncService stamps it, in bulk).
        return tap((new VendTransaction)->forceFill(array_merge([
            'order_id' => 'SSYNC'.(++$n),
            'vend_id' => 1320,
            'transaction_datetime' => Carbon::parse('2026-09-02 11:49:46'),
            'amount' => 200,
            'qty' => 1,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ], $attrs)))->save();
    }

    private function gatewayLog(int $status): int
    {
        static $n = 0;

        return PaymentGatewayLog::create([
            'order_id' => 'PGL'.(++$n),
            'amount' => 200,
            'payment_gateway_id' => 1,
            'status' => $status,
        ])->id;
    }

    /** Ids the index scope lists for a given filter value, in id order. */
    private function listed(string $filter): array
    {
        return VendTransaction::withoutGlobalScopes()
            ->filterTransactionIndex(new Request(['settle_sync' => $filter]), true)
            ->pluck('vend_transactions.id')
            ->sort()
            ->values()
            ->all();
    }

    public function test_filter_follows_the_settle_sync_column(): void
    {
        $cash = PaymentMethod::create(['name' => 'Cash', 'code' => 0]);
        $card = PaymentMethod::create(['name' => 'Card Terminal', 'code' => 1]);
        $qr = PaymentMethod::create(['name' => 'PayNow', 'code' => 2, 'payment_gateway_id' => 1]);

        $cardSynced = $this->txn(['payment_method_id' => $card->id, 'card_settlement_synced_at' => now()]);
        $cardPending = $this->txn(['payment_method_id' => $card->id]);
        $qrApproved = $this->txn(['payment_method_id' => $qr->id, 'payment_gateway_log_id' => $this->gatewayLog(PaymentGatewayLog::STATUS_APPROVE)]);
        $qrRefunded = $this->txn(['payment_method_id' => $qr->id, 'payment_gateway_log_id' => $this->gatewayLog(PaymentGatewayLog::STATUS_REFUND)]);
        $qrDeclined = $this->txn(['payment_method_id' => $qr->id, 'payment_gateway_log_id' => $this->gatewayLog(PaymentGatewayLog::STATUS_DECLINE)]);
        $qrNoLog = $this->txn(['payment_method_id' => $qr->id]);
        $this->txn(['payment_method_id' => $cash->id]);
        // A stray sync stamp on a cash row still leaves its cell blank.
        $this->txn(['payment_method_id' => $cash->id, 'card_settlement_synced_at' => now()]);

        $this->assertSame(
            collect([$cardSynced->id, $qrApproved->id, $qrRefunded->id])->sort()->values()->all(),
            $this->listed('true'),
        );

        $this->assertSame(
            collect([$cardPending->id, $qrDeclined->id, $qrNoLog->id])->sort()->values()->all(),
            $this->listed('false'),
        );

        $this->assertCount(8, $this->listed('all'));
    }
}
