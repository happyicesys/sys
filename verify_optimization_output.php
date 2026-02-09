#!/usr/bin/env php
<?php

/**
 * Verification Script: Compare Old vs New calculateSuccessfulItemCount
 *
 * This script verifies that the optimized SQL-based method produces
 * identical results to the original PHP-based method.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Vend;
use App\Models\VendTransaction;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "========================================\n";
echo "Optimization Verification Test\n";
echo "========================================\n";
echo "\n";

// Function: NEW optimized method (SQL aggregation)
function calculateSuccessfulItemCountNew($transactionQuery): int
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

// Function: OLD method (PHP iteration)
function calculateSuccessfulItemCountOld($transactionQuery): int
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

// Test with real data
echo "Testing with real vends...\n\n";

$vends = Vend::where('is_active', true)
    ->whereHas('vendTransactions', function ($q) {
        $q->where('transaction_datetime', '>=', now()->subDays(1));
    })
    ->limit(10)
    ->get();

if ($vends->count() === 0) {
    echo "❌ No active vends with recent transactions found.\n";
    echo "   Please run this script when there is transaction data.\n\n";
    exit(1);
}

echo "Found {$vends->count()} vends to test.\n\n";
echo str_pad("Vend ID", 10) . str_pad("Code", 15) . str_pad("Old Method", 15) . str_pad("New Method", 15) . str_pad("Match", 10) . str_pad("Time Old", 12) . str_pad("Time New", 12) . "\n";
echo str_repeat("-", 100) . "\n";

$allMatch = true;
$totalTimeOld = 0;
$totalTimeNew = 0;

foreach ($vends as $vend) {
    $todayTxns = $vend->daysVendTransactions(0, 0);

    // Measure old method
    $startOld = microtime(true);
    $oldResult = calculateSuccessfulItemCountOld($todayTxns);
    $timeOld = (microtime(true) - $startOld) * 1000; // ms

    // Measure new method
    $startNew = microtime(true);
    $newResult = calculateSuccessfulItemCountNew($todayTxns);
    $timeNew = (microtime(true) - $startNew) * 1000; // ms

    $match = $oldResult === $newResult;
    $matchSymbol = $match ? '✅' : '❌';

    if (!$match) {
        $allMatch = false;
    }

    $totalTimeOld += $timeOld;
    $totalTimeNew += $timeNew;

    echo str_pad($vend->id, 10)
        . str_pad($vend->code, 15)
        . str_pad($oldResult, 15)
        . str_pad($newResult, 15)
        . str_pad($matchSymbol, 10)
        . str_pad(number_format($timeOld, 2) . 'ms', 12)
        . str_pad(number_format($timeNew, 2) . 'ms', 12)
        . "\n";
}

echo str_repeat("-", 100) . "\n";
echo str_pad("TOTAL", 40)
    . str_pad("", 15)
    . str_pad("", 10)
    . str_pad(number_format($totalTimeOld, 2) . 'ms', 12)
    . str_pad(number_format($totalTimeNew, 2) . 'ms', 12)
    . "\n";

$speedup = $totalTimeOld > 0 ? (($totalTimeOld - $totalTimeNew) / $totalTimeOld * 100) : 0;
echo "\nPerformance improvement: " . number_format($speedup, 1) . "%\n";

echo "\n";
echo "========================================\n";
if ($allMatch) {
    echo "✅ SUCCESS: All results match!\n";
    echo "   The optimization is safe to use.\n";
    echo "   Output is identical to before.\n";
} else {
    echo "❌ FAILURE: Some results don't match!\n";
    echo "   Please review the optimization.\n";
}
echo "========================================\n";
echo "\n";

exit($allMatch ? 0 : 1);
