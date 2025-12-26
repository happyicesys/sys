<?php

$customerName = 'zoo';
$customer = App\Models\Customer::where('name', 'like', "%$customerName%")->first();

if (!$customer) {
    echo "Customer not found\n";
} else {
    echo "Customer: " . $customer->name . " (ID: " . $customer->id . ")\n";

    // Monthly Sales
    echo "\n--- Monthly Sales (2024 vs 2025) ---\n";
    $monthly = App\Models\VendRecord::where('customer_id', $customer->id)
        ->whereIn('year', [2024, 2025])
        ->selectRaw('year, month, SUM(total_amount)/100 as amount, SUM(total_count) as count')
        ->groupBy('year', 'month')
        ->orderBy('year')
        ->orderBy('month')
        ->get();

    $data = [];
    foreach ($monthly as $m) {
        $data[$m->month][$m->year] = $m->amount;
    }

    echo str_pad("Month", 6) . " | " . str_pad("2024", 10) . " | " . str_pad("2025", 10) . " | " . "Diff (25-24)\n";
    echo str_repeat("-", 45) . "\n";

    for ($i = 1; $i <= 12; $i++) {
        $val24 = $data[$i][2024] ?? 0;
        $val25 = $data[$i][2025] ?? 0;
        $diff = $val25 - $val24;
        echo str_pad($i, 6) . " | " . str_pad(number_format($val24, 2), 10) . " | " . str_pad(number_format($val25, 2), 10) . " | " . number_format($diff, 2) . "\n";
    }

    // Daily Sales for Dec
    echo "\n--- Daily Sales (Dec 2024 vs Dec 2025) ---\n";
    $daily = App\Models\VendRecord::where('customer_id', $customer->id)
        ->whereIn('year', [2024, 2025])
        ->where('month', 12)
        ->selectRaw('year, month, day, SUM(total_amount)/100 as amount')
        ->groupBy('year', 'month', 'day')
        ->orderBy('day')
        ->get();

    $dailyData = [];
    foreach ($daily as $d) {
        $dailyData[$d->day][$d->year] = $d->amount;
    }

    echo str_pad("Day", 6) . " | " . str_pad("Dec 2024", 10) . " | " . str_pad("Dec 2025", 10) . " | " . "Diff\n";
    echo str_repeat("-", 45) . "\n";

    for ($i = 1; $i <= 31; $i++) {
        $val24 = $dailyData[$i][2024] ?? 0;
        $val25 = $dailyData[$i][2025] ?? 0;
        $diff = $val25 - $val24;
        echo str_pad($i, 6) . " | " . str_pad(number_format($val24, 2), 10) . " | " . str_pad(number_format($val25, 2), 10) . " | " . number_format($diff, 2) . "\n";
    }
}
