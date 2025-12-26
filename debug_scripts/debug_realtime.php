<?php

$customerName = 'zoo';
$customers = App\Models\Customer::where('name', 'like', "%$customerName%")
    ->orWhere('virtual_customer_prefix', 'like', "%$customerName%")
    ->orWhere('virtual_customer_code', 'like', "%$customerName%")
    ->pluck('id');

$vends = App\Models\Vend::whereIn('customer_id', $customers)->pluck('id');

echo "Checking real-time transactions for Dec 2025 (Zoo)...\n";

$transactions = App\Models\VendTransaction::whereIn('vend_id', $vends)
    ->whereBetween('transaction_datetime', ['2025-12-01 00:00:00', '2025-12-26 23:59:59'])
    ->selectRaw('DATE(transaction_datetime) as date, SUM(amount) as amount')
    ->groupBy('date')
    ->orderBy('date')
    ->get();

foreach ($transactions as $t) {
    echo $t->date . ": $ " . number_format($t->amount, 2) . "\n";
}

$totalRealtime = $transactions->sum('amount');
echo "Total Realtime Dec 2025: $" . number_format($totalRealtime, 2) . "\n";
