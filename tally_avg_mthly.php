<?php
/**
 * READ-ONLY diagnostic — Avg Mthly Sales tally between the Machine page
 * (Vend/CustomerIndex) and the Site Summary page (Customer/Summary).
 *
 * No writes, no migrations. Safe to delete after use.
 *
 * Usage:   php tally_avg_mthly.php [machineCode]
 * Default machineCode = 1184
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();   // console kernel — avoids Clockwork's HTTP 'request' dependency

$machineCode = $argv[1] ?? '1184';
$floorStr    = config('reporting.floor_date', '2023-01-01');
$floor       = Carbon::parse($floorStr)->startOfMonth();
$now         = Carbon::now();

$money = fn ($cents) => '$' . number_format($cents / 100, 2);

echo "================ Avg Mthly Sales tally ================\n";
echo "Machine code : {$machineCode}\n";
echo "Floor date   : {$floorStr}\n";
echo "Now          : {$now->toDateString()}\n\n";

// ---- Resolve the machine (vends.code or vends.id) ----------------------
$vend = DB::table('vends')
    ->where('code', $machineCode)
    ->orWhere('id', is_numeric($machineCode) ? (int) $machineCode : 0)
    ->select('id', 'code', 'customer_id', 'begin_date', 'vend_transaction_totals_json')
    ->first();

if (!$vend) {
    echo "!! No vend found for code/id {$machineCode}\n";
    exit(1);
}

$customer = DB::table('customers')
    ->where('id', $vend->customer_id)
    ->select('id', 'begin_date', 'totals_json')
    ->first();

echo "vends.id={$vend->id} code={$vend->code} customer_id={$vend->customer_id}\n";
echo "vends.begin_date     = {$vend->begin_date}\n";
echo "customers.begin_date = " . ($customer->begin_date ?? 'NULL') . "\n\n";

// ====================================================================
// METHOD A — Machine page (Vend/CustomerIndex.vue  avgMthlySales)
//   numerator   = vends.vend_transaction_totals_json->vend_records_amount_latest
//   denominator = inclusive calendar months begin(floored)->today
// ====================================================================
$vtt = json_decode($vend->vend_transaction_totals_json ?? '{}', true) ?: [];
$numA = (int) ($vtt['vend_records_amount_latest'] ?? 0);

$beginA = $vend->begin_date ? Carbon::parse($vend->begin_date)->startOfMonth() : null;
if (!$beginA || $beginA->lt($floor)) $beginA = $floor->copy();
$monthsA = max(1, ($now->year - $beginA->year) * 12 + ($now->month - $beginA->month) + 1);
$avgA = $numA / 100 / $monthsA;

echo "--- METHOD A: Machine page (lifetime / all months) ---\n";
echo "numerator (vend_records_amount_latest) = {$numA}  = " . $money($numA) . "\n";
echo "begin (floored)  = {$beginA->toDateString()}\n";
echo "month count      = {$monthsA}  (every calendar month begin->today, incl. empty)\n";
echo "AVG MTHLY (A)    = " . number_format($avgA, 2) . "\n\n";

// Cross-check the customer-level mirror numerator
$ctot = json_decode($customer->totals_json ?? '{}', true) ?: [];
$numCust = (int) ($ctot['vend_records_amount_latest'] ?? 0);
echo "(customers.totals_json mirror numerator = {$numCust} = " . $money($numCust) . ")\n\n";

// ====================================================================
// METHOD B — Site Summary page (Customer/Summary.vue avg_monthly_sales_cents)
//   numerator   = cumulative SUM(sales_cents) over customer_period_summaries
//   denominator = COUNT(DISTINCT year_month) with a summary row, from floor
// ====================================================================
$rows = DB::table('customer_period_summaries')
    ->where('customer_id', $vend->customer_id)
    ->where('year_month', '>=', $floor->toDateString())
    ->orderBy('year_month')
    ->get(['year_month', 'period_start', 'sales_cents']);

echo "--- METHOD B: Site Summary (sum sales_cents / distinct months w/ data) ---\n";
echo str_pad('year_month', 14) . str_pad('sales_cents', 14) . "sales\n";

$sumB = 0;
$months = [];
foreach ($rows as $r) {
    $ym = Carbon::parse($r->year_month)->toDateString();
    $months[$ym] = true;
    $sumB += (int) $r->sales_cents;
    echo str_pad($ym, 14) . str_pad((string) (int) $r->sales_cents, 14) . $money((int) $r->sales_cents) . "\n";
}
$monthsB = max(1, count($months));
$avgB = $sumB / 100 / $monthsB;

echo "\n";
echo "rows             = " . count($rows) . "\n";
echo "distinct months  = {$monthsB}\n";
echo "numerator sum    = {$sumB}  = " . $money($sumB) . "\n";
echo "AVG MTHLY (B)    = " . number_format($avgB, 2) . "\n\n";

// ====================================================================
// GAP DECOMPOSITION
// ====================================================================
echo "================ GAP ================\n";
echo "A (machine page) = " . number_format($avgA, 2) . "\n";
echo "B (site summary) = " . number_format($avgB, 2) . "\n";
echo "B - A            = " . number_format($avgB - $avgA, 2) . "\n\n";
echo "numerator A - B  = " . $money($numA - $sumB) . "  (A " . $money($numA) . " vs B " . $money($sumB) . ")\n";
echo "denominator A,B  = {$monthsA} vs {$monthsB}  (diff " . ($monthsA - $monthsB) . " months)\n\n";
echo "If numerators were equal, B's avg with A's denom = " . number_format($sumB / 100 / $monthsA, 2) . "\n";
echo "If denoms were equal, A's avg with B's denom    = " . number_format($numA / 100 / $monthsB, 2) . "\n";
echo "=====================================\n\n";

// ====================================================================
// METHOD C — per-month numerator source comparison
//   vend_records.total_amount (the totals_json source, by calendar month)
//   vs customer_period_summaries.sales_cents (the Summary source)
//   to localise where the numerator gap lives.
// ====================================================================
echo "--- METHOD C: monthly source comparison (vend_records vs period_summaries) ---\n";
echo str_pad('month', 12) . str_pad('vend_records', 16) . str_pad('period_summary', 16) . "diff\n";

// vend_records monthly totals for this customer, from the floor month onward.
$vrRows = DB::table('vend_records')
    ->where('customer_id', $vend->customer_id)
    ->where('date', '>=', $floor->toDateString())
    ->selectRaw("DATE_FORMAT(`date`, '%Y-%m-01') AS ym, SUM(total_amount) AS amt")
    ->groupBy('ym')
    ->pluck('amt', 'ym');

$psRows = [];
foreach ($rows as $r) {
    $psRows[Carbon::parse($r->year_month)->format('Y-m-01')] = (int) $r->sales_cents;
}

$allMonths = array_unique(array_merge(array_keys($vrRows->toArray()), array_keys($psRows)));
sort($allMonths);
$vrTotal = 0; $psTotal = 0;
foreach ($allMonths as $m) {
    $vr = (int) ($vrRows[$m] ?? 0);
    $ps = (int) ($psRows[$m] ?? 0);
    $vrTotal += $vr; $psTotal += $ps;
    $flag = $vr === $ps ? '' : '  <-- differs';
    echo str_pad($m, 12) . str_pad((string) $vr, 16) . str_pad((string) $ps, 16) . ($ps - $vr) . $flag . "\n";
}
echo "\n";
echo "vend_records total   = " . $money($vrTotal) . "\n";
echo "period_summary total = " . $money($psTotal) . "\n";
echo "diff (PS - VR)       = " . $money($psTotal - $vrTotal) . "\n";
echo "(note: totals_json adds today's vend_transactions on top of vend_records lifetime)\n";
echo "=====================================\n\n";

// ====================================================================
// METHOD D — drill-down on the worst discrepant month (default 2024-11)
//   Compare the frozen vend_records daily snapshot against the live
//   vend_transactions for the same month, to prove the mechanism.
// ====================================================================
$drillMonth = $argv[2] ?? '2024-11';
$dStart = Carbon::parse($drillMonth . '-01')->startOfMonth();
$dEnd   = $dStart->copy()->endOfMonth()->endOfDay();
echo "--- METHOD D: drill-down on {$drillMonth} (customer_id={$vend->customer_id}) ---\n\n";

echo "vend_records rows in {$drillMonth} (frozen daily snapshot):\n";
$vrDays = DB::table('vend_records')
    ->where('customer_id', $vend->customer_id)
    ->whereBetween('date', [$dStart->toDateString(), $dEnd->toDateString()])
    ->orderBy('date')
    ->get(['date', 'vend_id', 'total_amount', 'created_at', 'updated_at']);
echo "  row count = " . count($vrDays) . "\n";
foreach ($vrDays as $d) {
    echo "  " . $d->date . "  vend={$d->vend_id}  amt=" . $money((int) $d->total_amount)
        . "  created={$d->created_at}  updated={$d->updated_at}\n";
}

echo "\nvend_transactions in {$drillMonth} (live source of truth):\n";
$txAgg = DB::table('vend_transactions')
    ->where('customer_id', $vend->customer_id)
    ->whereBetween('transaction_datetime', [$dStart, $dEnd])
    ->selectRaw('settlement_status, COUNT(*) AS cnt, SUM(amount) AS amt,
                 COUNT(DISTINCT DATE(transaction_datetime)) AS days,
                 MIN(created_at) AS first_created, MAX(created_at) AS last_created')
    ->groupBy('settlement_status')
    ->get();
$statusName = [0 => 'PENDING', 1 => 'REFUNDED', 2 => 'SETTLED'];
foreach ($txAgg as $t) {
    $nm = $statusName[(int) $t->settlement_status] ?? ('status' . $t->settlement_status);
    echo "  {$nm}: cnt={$t->cnt}  sum=" . $money((int) $t->amt)
        . "  distinct_days={$t->days}  created {$t->first_created} .. {$t->last_created}\n";
}

// When did StoreVendsRecord-style rollup last touch this month vs when txns settled?
echo "\nInterpretation hints:\n";
echo "  - If vend_records has few/old rows but vend_transactions(SETTLED) spans many days,\n";
echo "    the daily rollup was never (re)built after the txns settled => frozen-snapshot drift.\n";
echo "=====================================\n";
