<?php

$customerName = 'zoo';
$customers = App\Models\Customer::where('name', 'like', "%$customerName%")
    ->orWhere('virtual_customer_prefix', 'like', "%$customerName%")
    ->orWhere('virtual_customer_code', 'like', "%$customerName%")
    ->pluck('id');

$totals = App\Models\VendRecord::whereIn('customer_id', $customers)
    ->whereIn('year', [2024, 2025])
    ->whereIn('month', [11, 12])
    ->selectRaw('year, month, SUM(total_amount)/100 as amount')
    ->groupBy('year', 'month')
    ->get();

foreach ($totals as $t) {
    echo "{$t->month}-{$t->year}: " . number_format($t->amount, 2) . "\n";
}
