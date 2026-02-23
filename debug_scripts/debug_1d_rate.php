<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Vend;
use App\Models\VendChannelErrorLog;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$vendCode = '2704';
$vend = Vend::where('code', $vendCode)->first();

if (!$vend) {
    echo "Vend $vendCode not found\n";
    exit;
}

echo "Vend ID: " . $vend->id . "\n";

$todayRaw = Carbon::parse('2026-01-13');
$today = Carbon::today(); // 2026-01-13 00:00:00

echo "Checking for date: " . $today->toDateTimeString() . "\n";

$channelErrors = VendChannelErrorLog::whereHas('vendChannel', function ($q) use ($vend) {
    $q->where('vend_id', $vend->id);
})
    ->where('created_at', '>=', $today)
    ->whereNull('vend_transaction_id')
    ->count();

echo "Standalone Channel Errors (Today): " . $channelErrors . "\n";

$txns = $vend->vendTransactions()
    ->where('transaction_datetime', '>=', $today->startOfDay())
    ->where('transaction_datetime', '<=', $today->endOfDay())
    ->get();

$txnCount = $txns->count();
echo "Transaction Count (Today): " . $txnCount . "\n";

$txnQtySum = $txns->sum(function($txn) {
    if ($txn->is_multiple == 1) {
        return $txn->qty ?? 0;
    } else {
        return $txn->qty ? $txn->qty : 1;
    }
});

echo "Transaction Qty Sum (Today): " . $txnQtySum . "\n";

$txnErrors = $txns->filter(function($txn) {
    $json = $txn->vend_transaction_json;
    $sErr = $json['SErr'] ?? null;
    $getType = $json['GET_TYPE'] ?? null;

    // In scopeIsError: whereNot('SErr', 0) orWhereNot('GET_TYPE', 1)
    if ($sErr != 0) return true;
    if ($getType != 1) return true;
    return false;
})->count();

echo "Transaction Errors (Today): " . $txnErrors . "\n";

$totalAllCount = $txnQtySum + $channelErrors;
$totalErrorCount = $txnErrors + $channelErrors;

echo "Calculated 1d All Count: " . $totalAllCount . "\n";
echo "Calculated 1d Error Count: " . $totalErrorCount . "\n";
echo "Calculated 1d Rate: " . ($totalAllCount > 0 ? ($totalErrorCount / $totalAllCount) * 100 : 0) . "%\n";
