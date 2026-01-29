<?php

use App\Models\Product;
use App\Models\VendTransaction;
use App\Services\VendTransactionSalesAggregator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$productId = 789; // U-84
$date = '2026-01-29';
$startDate = Carbon::parse($date)->subDays(6)->startOfDay(); // 2026-01-23 00:00:00
$endDate = Carbon::parse($date)->endOfDay(); // 2026-01-29 23:59:59

echo "Date Range: " . $startDate->toDateTimeString() . " to " . $endDate->toDateTimeString() . "\n";

$aggregatorResult = VendTransactionSalesAggregator::productTotals($startDate, $endDate)
    ->where('product_id', $productId)
    ->first();

echo "Aggregator Total Count: " . ($aggregatorResult ? $aggregatorResult->total_count : 0) . "\n";

// User Claim: 322 items
// Aggregator Found: 257 items (approx)
// Difference: ~65 items filtered out?

// Let's emulate the exact query the Transaction View uses to get "Purchased: 322".
// This usually just sums 'qty' without complex filtering.

$userViewQuery = DB::table('vend_transactions')
    ->whereBetween('transaction_datetime', [$startDate, $endDate])
    ->where('product_id', $productId);

$userTotal = $userViewQuery->sum('qty');
echo "User View (Simple Query) Total: $userTotal\n";

// But wait, the User view screenshot shows "Multiple Purchases Count: 36".
// So we must add Multi items too.

$userMultiItems = DB::table('vend_transaction_items')
    ->join('vend_transactions', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
    ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
    ->where('vend_transaction_items.product_id', $productId)
    ->count();

echo "User View Multi Items: $userMultiItems\n";

$totalUserPotential = $userTotal + $userMultiItems;

// Check Potentially Missed Gap (UTC Jan 22 16:00 to Jan 23 00:00) which is part of Jan 23 SG.
$gapStart = Carbon::parse('2026-01-22 16:00:00');
$gapEnd = Carbon::parse('2026-01-23 00:00:00');

$gapUserSingle = DB::table('vend_transactions')
    ->whereBetween('transaction_datetime', [$gapStart, $gapEnd])
    ->where('product_id', $productId)
    ->sum('qty');

$gapUserMulti = DB::table('vend_transaction_items')
    ->join('vend_transactions', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
    ->whereBetween('vend_transactions.transaction_datetime', [$gapStart, $gapEnd])
    ->where('vend_transaction_items.product_id', $productId)
    ->count();

$gapTotal = $gapUserSingle + $gapUserMulti;
echo "Gap (SGT Start of Day) Potential: $gapTotal\n";

$grandTotalPotentiallyVisible = $totalUserPotential + $gapTotal;
echo "Grand Total Visible (Local Time): $grandTotalPotentiallyVisible\n";

// Now apply Aggregator logic to this Gap too?
// The Aggregator INCLUDES this gap now (after my fix).
// So 'Aggregator Final' below should be compared to 'Grand Total Visible'.


$filteredSingle = DB::table('vend_transactions')
    ->whereBetween('transaction_datetime', [$startDate, $endDate])
    ->where('product_id', $productId)
    ->where(function ($q) {
        $q->whereNotNull('error_code_normalized')
            ->whereNotIn('error_code_normalized', [0, 6]);
    })
    ->sum('qty');

echo "Excluded Singles (Bad Error Code): $filteredSingle\n";

$filteredMulti = DB::table('vend_transaction_items')
    ->join('vend_transactions', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
    ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
    ->where('vend_transaction_items.product_id', $productId)
    ->whereNotNull('vend_transaction_items.vend_channel_error_code')
    ->whereNotIn('vend_transaction_items.vend_channel_error_code', [0, 6])
    ->count();

echo "Excluded Multi (Bad Error Code): $filteredMulti\n";

// Check if User View includes "Refunded"?
// Maybe User View includes is_refunded=1, but Aggregator doesn't?
// Aggregator doesn't seem to check 'is_refunded' explicitly in the `productTotals` function code I saw. It just checks Error Codes.
// But wait, does Error Code cover Refunded?
// Let's check Refunded counts.

$refundedSingle = DB::table('vend_transactions')
    ->whereBetween('transaction_datetime', [$startDate, $endDate])
    ->where('product_id', $productId)
    ->where('is_refunded', 1)
    ->sum('qty');

echo "Refunded Singles: $refundedSingle\n";

$refundedMulti = DB::table('vend_transaction_items')
    ->join('vend_transactions', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
    ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
    ->where('vend_transaction_items.product_id', $productId)
    ->where('vend_transaction_items.is_refunded', 1)
    ->count();

echo "Refunded Multi Items: $refundedMulti\n";

// Another check: Multiple flag on Main transaction vs Items.
// If vend_transactions.is_multiple = 1, but no items found?
// Or items found but product_id mismatch?

// Let's check Aggregator result again carefully.
$aggregatorResult = VendTransactionSalesAggregator::productTotals($startDate, $endDate)
    ->where('product_id', $productId)
    ->first();
echo "Aggregator Final: " . ($aggregatorResult ? $aggregatorResult->total_count : 0) . "\n";


// Simulate Job Logic exactly
$counts = VendTransactionSalesAggregator::productTotals($startDate, $endDate)
    ->pluck('total_count', 'product_id');

echo "Count for 826 in Job Logic: " . ($counts[826] ?? 'Not Found') . "\n";

$product = Product::find(826);
$count = $counts[826] ?? 0;
$avg = $count / 7;
echo "Calculated Avg: $avg\n";
echo "Cast to Int: " . (int) $avg . "\n";

// Dry run update
echo "Current DB Value: " . $product->avg_seven_days_count . "\n";
$product->avg_seven_days_count = $avg;
$product->save();
echo "New DB Value (after save): " . $product->fresh()->avg_seven_days_count . "\n";
