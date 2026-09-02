<?php

namespace Tests\Feature;

use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Support\SaleFacts;
use App\Support\SaleStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Sales Transactions "Dispense Status" filter (request key `is_payment_received`,
 * kept for bookmarked URLs).
 *
 * The filter used to short-circuit on `vend_transactions.is_payment_received`,
 * which VendTransactionService::processMapping forces TRUE for every QR gateway
 * sale regardless of the machine's verdict — so a failed QR vend was listed under
 * "Successful" while the grid showed its channel error. The filter now judges
 * what the Dispense Status column shows: the header error code for a single
 * vend, the items for a multiple (a partial basket matches both sides).
 */
class TransactionIndexDispenseFilterTest extends TestCase
{
    use RefreshDatabase;

    private VendChannelError $ok;

    private VendChannelError $fault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ok = VendChannelError::create(['code' => 0, 'desc' => 'No Malfunction (0)']);
        VendChannelError::create(['code' => 6, 'desc' => 'Microswitch pressed over time (6)']);
        $this->fault = VendChannelError::create(['code' => 4, 'desc' => 'Open circuit, motor not detected (4)']);
    }

    private function txn(array $attrs): VendTransaction
    {
        static $n = 0;

        return VendTransaction::create(array_merge([
            'order_id' => 'DISP'.(++$n),
            'vend_id' => 1320,
            'transaction_datetime' => Carbon::parse('2026-09-02 11:49:46'),
            'amount' => 200,
            'qty' => 1,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ], $attrs));
    }

    private function item(VendTransaction $txn, ?int $code): void
    {
        VendTransactionItem::create([
            'vend_transaction_id' => $txn->id,
            'vend_channel_code' => 11,
            'vend_channel_error_code' => $code,
            'vend_channel_error_id' => $code === null ? null : VendChannelError::where('code', $code)->value('id'),
        ]);
    }

    /** Ids the index scope lists for a given filter value, in id order. */
    private function listed(string $filter): array
    {
        $request = new Request(['is_payment_received' => $filter]);

        return VendTransaction::withoutGlobalScopes()
            ->filterTransactionIndex($request, true)
            ->pluck('vend_transactions.id')
            ->sort()
            ->values()
            ->all();
    }

    public function test_filter_follows_the_dispense_verdict_not_the_payment_flag(): void
    {
        $singleOk = $this->txn(['vend_channel_error_id' => $this->ok->id, 'is_payment_received' => true]);
        $singleFailed = $this->txn(['vend_channel_error_id' => $this->fault->id, 'is_payment_received' => false]);
        // The regression: QR gateway sale, motor fault, but processMapping flagged it paid.
        $qrFailed = $this->txn(['vend_channel_error_id' => $this->fault->id, 'is_payment_received' => true]);
        // Legacy single rows carry no code: the machine reported no fault.
        $legacy = $this->txn(['vend_channel_error_id' => null, 'is_payment_received' => null]);

        $partial = $this->txn(['is_multiple' => true, 'qty' => 2, 'is_payment_received' => true]);
        $this->item($partial, 0);
        $this->item($partial, 4);

        $allFailed = $this->txn(['is_multiple' => true, 'qty' => 2, 'is_payment_received' => true]);
        $this->item($allFailed, 4);
        $this->item($allFailed, 4);

        $allOk = $this->txn(['is_multiple' => true, 'qty' => 2, 'is_payment_received' => true]);
        $this->item($allOk, 6);
        $this->item($allOk, null);

        // No verdict yet: the column says "No report" / "Pending" (SaleStatus::dispense),
        // so neither side of the filter may list them — their NULL codes must not
        // read as "no fault". (Prod Aug 2026: ~4.8k settled-unreported rows.)
        $noReport = $this->txn(['vend_channel_error_id' => null, 'is_found_in_transaction' => false, 'is_payment_received' => true]);
        $noReportMulti = $this->txn(['is_multiple' => true, 'qty' => 2, 'is_found_in_transaction' => false, 'is_payment_received' => true]);
        $pending = $this->txn(['vend_channel_error_id' => null, 'settlement_status' => VendTransaction::SETTLEMENT_PENDING, 'is_found_in_transaction' => false]);
        foreach ([$noReport, $noReportMulti, $pending] as $t) {
            $this->assertContains(
                SaleStatus::dispense(SaleFacts::fromRow($t->fresh())),
                [SaleStatus::NO_REPORT, SaleStatus::PENDING]
            );
        }

        $this->assertSame(
            collect([$singleOk->id, $legacy->id, $partial->id, $allOk->id])->sort()->values()->all(),
            $this->listed('true'),
            'Dispensed: clean singles, legacy no-code singles, and any basket with a dispensed line'
        );

        $this->assertSame(
            collect([$singleFailed->id, $qrFailed->id, $partial->id, $allFailed->id])->sort()->values()->all(),
            $this->listed('false'),
            'Failed: faulted singles (QR included), and any basket with a failed line'
        );

        $this->assertCount(10, $this->listed('all'));
    }

    /** The grid label and the filter must agree on the same rows. */
    public function test_filter_agrees_with_the_column_labels(): void
    {
        $qrFailed = $this->txn(['vend_channel_error_id' => $this->fault->id, 'is_payment_received' => true]);

        $this->assertSame(SaleStatus::FAILED, SaleStatus::dispense(SaleFacts::fromRow($qrFailed->fresh()->load('vendChannelError'))));
        $this->assertSame([$qrFailed->id], $this->listed('false'));
        $this->assertSame([], $this->listed('true'));
    }
}
