<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\Customer;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncVendTransactionTotalsJsonOptimizationTest extends TestCase
{
    /**
     * Test that the optimized calculateSuccessfulItemCount produces the same results
     * as the original implementation.
     */
    public function test_calculate_successful_item_count_produces_same_results()
    {
        // Get a real vend with transactions
        $vend = Vend::whereHas('vendTransactions')->first();

        if (!$vend) {
            $this->markTestSkipped('No vend with transactions found');
        }

        // Get today's transactions
        $todayTxns = $vend->daysVendTransactions(0, 0);

        // Calculate using the NEW optimized method (SQL aggregation)
        $newResult = $this->calculateSuccessfulItemCountNew($todayTxns);

        // Calculate using the OLD method (PHP iteration)
        $oldResult = $this->calculateSuccessfulItemCountOld($todayTxns);

        // They should be exactly the same
        $this->assertEquals(
            $oldResult,
            $newResult,
            "Optimized method returned {$newResult} but old method returned {$oldResult}. Results must match!"
        );

        echo "\n✅ Test passed! Both methods returned: {$newResult}\n";
    }

    /**
     * NEW optimized method (SQL aggregation)
     */
    private function calculateSuccessfulItemCountNew($transactionQuery): int
    {
        $result = $transactionQuery
            ->clone()
            ->leftJoin('vend_channel_errors', 'vend_transactions.vend_channel_error_id', '=', 'vend_channel_errors.id')
            ->selectRaw('
                SUM(
                    CASE
                        WHEN vend_transactions.success_qty IS NOT NULL AND vend_transactions.success_qty > 0
                            THEN vend_transactions.success_qty
                        WHEN vend_transactions.vend_channel_error_id IS NULL
                            OR vend_channel_errors.code IN (0, 6)
                            OR vend_transactions.is_multiple = 1
                            THEN COALESCE(vend_transactions.qty, 0)
                        ELSE 0
                    END
                ) as total_count
            ')
            ->value('total_count');

        return (int) ($result ?? 0);
    }

    /**
     * OLD method (PHP iteration) - for comparison
     */
    private function calculateSuccessfulItemCountOld($transactionQuery): int
    {
        return (int) $transactionQuery
            ->clone()
            ->with('vendChannelError:id,code')
            ->get([
                'id',
                'qty',
                'success_qty',
                'is_multiple',
                'vend_channel_error_id',
            ])
            ->sum(function ($transaction) {
                if ($transaction->success_qty !== null && (int) $transaction->success_qty > 0) {
                    return (int) $transaction->success_qty;
                }

                $errorCode = optional($transaction->vendChannelError)->code;

                if (
                    is_null($transaction->vend_channel_error_id) ||
                    in_array((int) $errorCode, [0, 6], true) ||
                    (bool) $transaction->is_multiple
                ) {
                    return (int) ($transaction->qty ?? 0);
                }

                return 0;
            });
    }

    /**
     * Test with multiple vends to ensure consistency
     */
    public function test_multiple_vends_produce_same_results()
    {
        $vends = Vend::whereHas('vendTransactions')
            ->where('is_active', true)
            ->limit(5)
            ->get();

        if ($vends->count() === 0) {
            $this->markTestSkipped('No active vends with transactions found');
        }

        $allMatch = true;
        $results = [];

        foreach ($vends as $vend) {
            $todayTxns = $vend->daysVendTransactions(0, 0);

            $newResult = $this->calculateSuccessfulItemCountNew($todayTxns);
            $oldResult = $this->calculateSuccessfulItemCountOld($todayTxns);

            $results[] = [
                'vend_id' => $vend->id,
                'vend_code' => $vend->code,
                'new' => $newResult,
                'old' => $oldResult,
                'match' => $newResult === $oldResult,
            ];

            if ($newResult !== $oldResult) {
                $allMatch = false;
            }
        }

        // Print results
        echo "\n";
        echo "Vend ID | Vend Code | New Method | Old Method | Match\n";
        echo "--------|-----------|------------|------------|------\n";
        foreach ($results as $result) {
            echo sprintf(
                "%-7s | %-9s | %-10s | %-10s | %s\n",
                $result['vend_id'],
                $result['vend_code'],
                $result['new'],
                $result['old'],
                $result['match'] ? '✅' : '❌'
            );
        }
        echo "\n";

        $this->assertTrue($allMatch, 'All vends should produce matching results');
    }

    /**
     * Test edge cases
     */
    public function test_edge_cases()
    {
        echo "\n=== Testing Edge Cases ===\n";

        // Test 1: Vend with no transactions today
        $vendNoTxns = Vend::whereDoesntHave('vendTransactions', function ($q) {
            $q->where('transaction_datetime', '>=', now()->startOfDay());
        })->first();

        if ($vendNoTxns) {
            $todayTxns = $vendNoTxns->daysVendTransactions(0, 0);
            $newResult = $this->calculateSuccessfulItemCountNew($todayTxns);
            $oldResult = $this->calculateSuccessfulItemCountOld($todayTxns);

            echo "No transactions: New={$newResult}, Old={$oldResult}, Match=" . ($newResult === $oldResult ? '✅' : '❌') . "\n";
            $this->assertEquals($oldResult, $newResult, 'No transactions case failed');
        }

        // Test 2: Transactions with success_qty
        $txnWithSuccessQty = VendTransaction::whereNotNull('success_qty')
            ->where('success_qty', '>', 0)
            ->first();

        if ($txnWithSuccessQty) {
            $vend = $txnWithSuccessQty->vend;
            $todayTxns = $vend->daysVendTransactions(0, 0);
            $newResult = $this->calculateSuccessfulItemCountNew($todayTxns);
            $oldResult = $this->calculateSuccessfulItemCountOld($todayTxns);

            echo "With success_qty: New={$newResult}, Old={$oldResult}, Match=" . ($newResult === $oldResult ? '✅' : '❌') . "\n";
            $this->assertEquals($oldResult, $newResult, 'success_qty case failed');
        }

        // Test 3: Transactions with errors
        $txnWithError = VendTransaction::whereNotNull('vend_channel_error_id')->first();

        if ($txnWithError) {
            $vend = $txnWithError->vend;
            $todayTxns = $vend->daysVendTransactions(0, 0);
            $newResult = $this->calculateSuccessfulItemCountNew($todayTxns);
            $oldResult = $this->calculateSuccessfulItemCountOld($todayTxns);

            echo "With errors: New={$newResult}, Old={$oldResult}, Match=" . ($newResult === $oldResult ? '✅' : '❌') . "\n";
            $this->assertEquals($oldResult, $newResult, 'Error case failed');
        }

        // Test 4: is_multiple transactions
        $txnMultiple = VendTransaction::where('is_multiple', 1)->first();

        if ($txnMultiple) {
            $vend = $txnMultiple->vend;
            $todayTxns = $vend->daysVendTransactions(0, 0);
            $newResult = $this->calculateSuccessfulItemCountNew($todayTxns);
            $oldResult = $this->calculateSuccessfulItemCountOld($todayTxns);

            echo "is_multiple: New={$newResult}, Old={$oldResult}, Match=" . ($newResult === $oldResult ? '✅' : '❌') . "\n";
            $this->assertEquals($oldResult, $newResult, 'is_multiple case failed');
        }

        echo "\n";
    }
}
