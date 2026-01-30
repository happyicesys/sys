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

$productId = 447; // U-12
$date = '2026-01-29'; // Yesterday
$startDate = Carbon::parse($date)->subDays(6)->startOfDay(); // Jan 23 00:00:00
$endDate = Carbon::parse($date)->endOfDay(); // Jan 29 23:59:59

echo "Date Range: " . $startDate->toDateTimeString() . " to " . $endDate->toDateTimeString() . "\n";

// 1. Aggregator Count (Product Demand) - The one used in the job
$aggregatorResult = VendTransactionSalesAggregator::productTotals($startDate, $endDate, null, true)
    ->where('product_id', $productId)
    ->first();

$aggregatorCount = $aggregatorResult ? $aggregatorResult->total_count : 0;
echo "Aggregator (productTotals includedAll=true) Total Count: " . $aggregatorCount . "\n";

// 2. User Manual Count (Simple Sum of Product) - Replicating your CSV "Sum"
// The user likely filtered the CSV for 'U-12' and counted rows.
// NOTE: Is_multiple rows appear as 1 row in CSV per item?
// The user said: "find out all the U-12 sku ... then the total is 2724"

// Let's emulate that exactly:
$csvReplication = DB::table('vend_transactions')
    ->leftJoin('vend_transaction_items', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
    ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
    ->where(function ($q) use ($productId) {
        $q->where('vend_transactions.product_id', $productId)
            ->orWhere('vend_transaction_items.product_id', $productId);
    })
    ->count(); // Counting rows that match U-12

// Wait, standard CSV export lines:
// Single: 1 row
// Multi: 1 row per item?
// If I search "U-12" in Excel, I find every row where U-12 is the product.
// So, we just count occurrences of U-12 in transactions or items.

$singleCount = DB::table('vend_transactions')
    ->whereBetween('transaction_datetime', [$startDate, $endDate])
    ->where('product_id', $productId)
    ->where(function ($q) {
        $q->whereNull('is_multiple')->orWhere('is_multiple', 0);
    })
    ->count(); // for singles, count rows (qty is usually 1, but check sum(qty)?)

$singleQty = DB::table('vend_transactions')
    ->whereBetween('transaction_datetime', [$startDate, $endDate])
    ->where('product_id', $productId)
    ->where(function ($q) {
        $q->whereNull('is_multiple')->orWhere('is_multiple', 0);
    })
    ->sum('qty');

$multiCount = DB::table('vend_transaction_items')
    ->join('vend_transactions', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
    ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
    ->where('vend_transaction_items.product_id', $productId)
    ->count();

$totalRows = $singleCount + $multiCount;
$totalQty = $singleQty + $multiCount;

echo "CSV Count Row Emulation (Rows): $totalRows\n";
echo "CSV Count Qty Emulation: $totalQty\n";

if ($aggregatorCount != $totalQty) {
    echo "Difference found: " . ($aggregatorCount - $totalQty) . "\n";

    // Check for 'Deleted' transactions or specific weird cases?
    // Check for "Test" machines? The Job filters out 'is_testing' machines. Check debug script

    $testingVends = DB::table('vends')->where('is_testing', true)->pluck('id');

    $aggregatorFilteredResult = VendTransactionSalesAggregator::productTotals($startDate, $endDate, function ($query) use ($testingVends) {
        $query->whereNotIn('vend_transactions.vend_id', $testingVends);
    }, true)
        ->where('product_id', $productId)
        ->first();

    $aggregatorFilteredCount = $aggregatorFilteredResult ? $aggregatorFilteredResult->total_count : 0;

    echo "Aggregator (Filtered Testing Vends): $aggregatorFilteredCount\n";

    // Identify the 17 items difference
    // Which IDs are in Aggregator but NOT in CSV Query?

    // Aggregator IDs (approximate logic, since aggregator simplifies things, we need to rebuild the query to get IDs)
    $aggregatorIds = DB::table('vend_transactions')
        ->whereBetween('transaction_datetime', [$startDate, $endDate])
        ->where(function ($q) use ($productId) {
            // Singles
            $q->where(function ($sub) use ($productId) {
                $sub->where('product_id', $productId)
                    ->where(function ($Check) {
                        $Check->whereNull('is_multiple')->orWhere('is_multiple', 0); });
            })
                // Multis (via join)
                ->orWhereExists(function ($sub) use ($productId) {
                $sub->select(DB::raw(1))
                    ->from('vend_transaction_items')
                    ->whereColumn('vend_transaction_items.vend_transaction_id', 'vend_transactions.id')
                    ->where('product_id', $productId);
            });
        })
        ->pluck('id');

    // CSV Query IDs (re-using logic)
    // Actually the CSV emulation I wrote above is:
    // Single Transactions + Multi Transactions (via Items)

    // Let's check Singles logic difference
    // If sum matches, then why is total different?
    // Wait, Aggregator Total Count: 2741
    // CSV Emulation: 2724
    // Difference: 17

    // Attempt 1: Aggregator Combined Logic vs Split Logic I just wrote
    // The previous block output:
    // Aggregator Singles: 2342
    // CSV Singles: 2342
    // Aggregator Multis: 382
    // CSV Multis: 382
    // Total: 2342 + 382 = 2724.

    // BUT "Aggregator (productTotals includedAll=true) Total Count" at the top said 2741.
    // So my emulation of Aggregator logic is slightly wrong compared to actual execution.
    // Let's look at Aggregator Code again.

    // Actual Loop from Aggregator (Singles):
    // leftJoin('vend_channels as single_vc', ...)
    // where(function ($query) {
    //      $query->where('vend_transactions.is_multiple', false)
    //            ->orWhereNull('vend_transactions.is_multiple');
    // })
    // selectRaw('SUM(COALESCE(vend_transactions.qty, 1)) as total_count')

    // My Emulation was: count() (rows).
    // Maybe some singles have qty > 1?

    // Aggregator Singles:
    $aggSingles = DB::table('vend_transactions')
        ->leftJoin('vend_channels as single_vc', 'single_vc.id', '=', 'vend_transactions.vend_channel_id')
        ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
        ->where(function ($query) {
            $query->where('vend_transactions.is_multiple', false)
                ->orWhereNull('vend_transactions.is_multiple');
        })
        ->whereRaw('COALESCE(vend_transactions.product_id, single_vc.product_id) = ?', [$productId])
        // ->whereNull/In error_code (SKIPPED because includeAll=true)
        ->count();

    $singlesQtySum = DB::table('vend_transactions')
        ->leftJoin('vend_channels as single_vc', 'single_vc.id', '=', 'vend_transactions.vend_channel_id')
        ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
        ->where(function ($query) {
            $query->where('vend_transactions.is_multiple', false)
                ->orWhereNull('vend_transactions.is_multiple');
        })
        ->whereRaw('COALESCE(vend_transactions.product_id, single_vc.product_id) = ?', [$productId])
        ->sum(DB::raw('COALESCE(vend_transactions.qty, 1)'));

    echo "Actual Aggregator Singles Sum(Qty): $singlesQtySum\n";

    if ($singlesQtySum > $aggSingles) {
        echo "Found Singles with Qty > 1. Total Impact: " . ($singlesQtySum - $aggSingles) . "\n";
    }

    // What about multi?
    // Aggregator Multi Logic:
    // selectRaw('COUNT(*) as total_count') IF includeAll=true

    // My emulation used count().

    $multisCountActual = DB::table('vend_transactions')
        ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
        ->where('vend_transactions.is_multiple', true)
        ->join('vend_transaction_items as vti', 'vti.vend_transaction_id', '=', 'vend_transactions.id')
        ->leftJoin('vend_channels as multi_vc', 'multi_vc.id', '=', 'vti.vend_channel_id')
        ->whereRaw('COALESCE(vti.product_id, multi_vc.product_id) = ?', [$productId])
        ->count();

    echo "Actual Aggregator Multi Count: $multisCountActual\n";

    // Aggregator Singles:
    $aggSingles = DB::table('vend_transactions')
        ->leftJoin('vend_channels as single_vc', 'single_vc.id', '=', 'vend_transactions.vend_channel_id')
        ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
        ->where(function ($query) {
            $query->where('vend_transactions.is_multiple', false)
                ->orWhereNull('vend_transactions.is_multiple');
        })
        ->whereRaw('COALESCE(vend_transactions.product_id, single_vc.product_id) = ?', [$productId])
        // ->whereNull/In error_code (SKIPPED because includeAll=true)
        ->count();

    echo "Aggregator Singles: $aggSingles\n";
    echo "CSV Singles: $singleQty\n";

    // Aggregator Multis:
    $aggMultis = DB::table('vend_transactions')
        ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
        ->where('vend_transactions.is_multiple', true)
        ->join('vend_transaction_items as vti', 'vti.vend_transaction_id', '=', 'vend_transactions.id')
        ->leftJoin('vend_channels as multi_vc', 'multi_vc.id', '=', 'vti.vend_channel_id')
        ->whereRaw('COALESCE(vti.product_id, multi_vc.product_id) = ?', [$productId])
        ->count();

    echo "Aggregator Multis: $aggMultis\n";
    echo "CSV Multis: $multiCount\n";

    // Difference Analysis
    if ($aggSingles > $singleQty) {
        echo "Difference is in SINGLES (" . ($aggSingles - $singleQty) . "). Likely `vend_channels` fallback for product_id.\n";
        // Check fallback rows
        $fallbackRows = DB::table('vend_transactions')
            ->leftJoin('vend_channels as single_vc', 'single_vc.id', '=', 'vend_transactions.vend_channel_id')
            ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where('vend_transactions.is_multiple', false)
                    ->orWhereNull('vend_transactions.is_multiple');
            })
            ->whereNull('vend_transactions.product_id')
            ->where('single_vc.product_id', $productId)
            ->count();
        echo "Singles using Fallback Product ID: $fallbackRows\n";
    }

    // Hypothesis: Overlap in CSV Emulation Logic?
    // Are there transactions with `is_multiple=0` (or null) that ALSO have entries in `vend_transaction_items`?

    $overlapCount = DB::table('vend_transaction_items')
        ->join('vend_transactions', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
        ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
        ->where('vend_transaction_items.product_id', $productId)
        ->where(function ($q) {
            $q->where('vend_transactions.is_multiple', false)
                ->orWhereNull('vend_transactions.is_multiple');
        })
        ->count();

    echo "Overlap (Items exists for Non-Multiple Trans): $overlapCount\n";

    // Aggregator Total: 2741 (Actual execution)
    // My manual sum of Agg Parts: 2342 + 382 = 2724.
    // This implies that "2741" comes from somewhere else... or my "Actual Aggregator Singles Sum" query is missing something that the real Aggregator catches.

    // Real Aggregator uses `unionAll`.
    // Maybe `vend_transactions.qty` is NULL and defaults to 1, but I am summing COALESCE(qty, 1).

    // Let's run the exact query builder from the service to see what it generates.

    $builder = VendTransactionSalesAggregator::productTotals($startDate, $endDate, null, true);
    // $sql = $builder->toSql();
    // $bindings = $builder->getBindings();
    // echo "SQL: $sql\n";

    // Let's verify the result of the builder for our product.
    $res = $builder->where('product_id', $productId)->first();
    echo "Direct Builder Result: " . ($res ? $res->total_count : 0) . "\n";

    // If Direct Builder says 2741, and my manual sum says 2724, then my manual sum queries are flawed.
    // Difference is 17.

    // Is it possible "is_multiple" = 1, but NO items? The Aggregator Multi logic counts inner join on items.

    // Is it possible "Singles" query picks up something more?
    // Aggregator Singles:
    // leftJoin('vend_channels as single_vc', ...)
    // selectRaw('COALESCE(vend_transactions.product_id, single_vc.product_id) as product_id')

    // Maybe transactions have NO product_id on transaction, NO product_id on channel, but strict join? No, it's left join.
    // groupBy product_id.

    // What if `vend_transactions.product_id` is DIFFERENT from `single_vc.product_id`?
    // The COALESCE prioritizes `vend_transactions.product_id`.

    // Let's check if there are transactions where:
    // 1. Transaction Product ID is NULL
    // 2. Channel Product ID IS our Product (447)
    // AND `qty` > 1?

    $defaults = DB::table('vend_transactions')
        ->leftJoin('vend_channels as single_vc', 'single_vc.id', '=', 'vend_transactions.vend_channel_id')
        ->whereBetween('vend_transactions.transaction_datetime', [$startDate, $endDate])
        ->where(function ($query) {
            $query->where('vend_transactions.is_multiple', false)
                ->orWhereNull('vend_transactions.is_multiple');
        })
        ->whereNull('vend_transactions.product_id')
        ->where('single_vc.product_id', $productId)
        ->count();

    // Investigation: Why does Builder give 2741 but my replication give 2724?
    // Let's dump the SQL to see the difference.

    $builder = VendTransactionSalesAggregator::productTotals($startDate, $endDate, null, true);
    $sql = $builder->toSql();
    echo "Builder SQL: $sql\n";
    // echo "Bindings: " . implode(', ', $builder->getBindings()) . "\n";

    // One difference might be Timezone handling.
    // My script uses $startDate and $endDate which are Carbon instances.
    // The Aggregator converts them:
    // $start = $start->copy()->setTimezone(config('app.timezone'))->startOfDay()->setTimezone('UTC');

    // In strict testing:
    // My $startDate is '2026-01-23 00:00:00' (Local Time Check: app.timezone is likely Asia/Singapore)
    // The Aggregator does `setTimezone(config('app.timezone'))`...

    echo "App Timezone: " . config('app.timezone') . "\n";
    echo "Script StartDate: " . $startDate->format('Y-m-d H:i:s') . " (Tz: " . $startDate->timezoneName . ")\n";

    // Replicate Aggregator Date Logic exactly
    $aggStart = $startDate->copy()->setTimezone(config('app.timezone'))->startOfDay()->setTimezone('UTC');
    $aggEnd = $endDate->copy()->setTimezone(config('app.timezone'))->endOfDay()->setTimezone('UTC');

    echo "Aggregator Effective Start (UTC): " . $aggStart->format('Y-m-d H:i:s') . "\n";
    echo "Aggregator Effective End (UTC): " . $aggEnd->format('Y-m-d H:i:s') . "\n";

    // Now check if my replication queries used the *Original* $startDate (Local/UTC?) or the converted one?
    // Laravel `whereBetween` handles Carbon.
    // If $startDate has a timezone, Laravel converts it to DB timezone (UTC usually).

    // Let's force my replication queries to use the EXACT UTC strings the Aggregator uses.

    $repSingles = DB::table('vend_transactions')
        ->leftJoin('vend_channels as single_vc', 'single_vc.id', '=', 'vend_transactions.vend_channel_id')
        ->whereBetween('vend_transactions.transaction_datetime', [$aggStart, $aggEnd]) // Use Aggregator Time
        ->where(function ($query) {
             $query->where('vend_transactions.is_multiple', false)
                   ->orWhereNull('vend_transactions.is_multiple');
        })
        ->whereRaw('COALESCE(vend_transactions.product_id, single_vc.product_id) = ?', [$productId])
        ->sum(DB::raw('COALESCE(vend_transactions.qty, 1)'));

    $repMultis = DB::table('vend_transactions')
        ->whereBetween('vend_transactions.transaction_datetime', [$aggStart, $aggEnd]) // Use Aggregator Time
        ->where('vend_transactions.is_multiple', true)
        ->join('vend_transaction_items as vti', 'vti.vend_transaction_id', '=', 'vend_transactions.id')
        ->leftJoin('vend_channels as multi_vc', 'multi_vc.id', '=', 'vti.vend_channel_id')
        ->whereRaw('COALESCE(vti.product_id, multi_vc.product_id) = ?', [$productId])
        ->count();

    echo "Replication with Strict UTC Dates - Singles: $repSingles\n";
    echo "Replication with Strict UTC Dates - Multis: $repMultis\n";
    echo "Replication Strict Total: " . ($repSingles + $repMultis) . "\n";
}
