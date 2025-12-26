<?php

$customerName = 'zoo';
$customers = App\Models\Customer::where('name', 'like', "%$customerName%")
    ->orWhere('virtual_customer_prefix', 'like', "%$customerName%")
    ->orWhere('virtual_customer_code', 'like', "%$customerName%")
    ->pluck('id');

echo "Analyzing Feb 2024 vs Feb 2025 for " . $customers->count() . " customers...\n";

$data = App\Models\VendRecord::whereIn('customer_id', $customers)
    ->whereIn('year', [2024, 2025])
    ->where('month', 2)
    ->selectRaw('vend_id, year, SUM(total_amount)/100 as amount')
    ->groupBy('vend_id', 'year')
    ->get();

$comparison = [];

foreach ($data as $row) {
    if (!isset($comparison[$row->vend_id])) {
        $comparison[$row->vend_id] = ['2024' => 0, '2025' => 0, 'name' => ''];
    }
    $comparison[$row->vend_id][$row->year] = $row->amount;
}

// Fetch vend names
$vendIds = array_keys($comparison);
$vends = App\Models\Vend::whereIn('id', $vendIds)->pluck('name', 'id');
$vendCodes = App\Models\Vend::whereIn('id', $vendIds)->pluck('code', 'id');

foreach ($comparison as $id => &$vals) {
    $vals['name'] = ($vendCodes[$id] ?? $id) . ' - ' . ($vends[$id] ?? 'Unknown');
    $vals['diff'] = $vals['2025'] - $vals['2024'];
}

// Sort by biggest drop (ascending difference)
usort($comparison, function ($a, $b) {
    return $a['diff'] <=> $b['diff'];
});

echo str_pad("Machine", 50) . " | " . str_pad("Feb 2024", 10) . " | " . str_pad("Feb 2025", 10) . " | " . "Diff\n";
echo str_repeat("-", 85) . "\n";

foreach ($comparison as $row) {
    // Only show significant differences
    if (abs($row['diff']) > 100) {
        echo str_pad(substr($row['name'], 0, 48), 50) . " | " .
            str_pad(number_format($row['2024'], 2), 10) . " | " .
            str_pad(number_format($row['2025'], 2), 10) . " | " .
            number_format($row['diff'], 2) . "\n";
    }
}
