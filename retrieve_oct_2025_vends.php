<?php
use App\Models\Vend;
use App\Models\VendRecord;

$year = 2025;
$month = 10;

// Get testing vend IDs
$testingVendIds = Vend::where('is_testing', true)->pluck('id')->toArray();

// Get the latest date with records for Oct 2025
$maxDate = VendRecord::where('year', $year)->where('month', $month)->max('date');

if (!$maxDate) {
    echo "No records found for Oct 2025\n";
    exit;
}

echo "Max Date: " . $maxDate . "\n";

// Login as user 1 (Brian) so Auth checks work if needed, though we will manually filter
Auth::loginUsingId(1);
$user = auth()->user();

// Determine allowed operators based on DashboardController logic
$allowedOperatorIds = [];
if ($user->operator_id == 1) { // Assuming ID 1 is HIPL
    $allowedOperatorIds = [
        1,
        \App\Models\Operator::where('code', 'HIMD')->first()?->id,
        \App\Models\Operator::where('code', 'LEA')->first()?->id,
        \App\Models\Operator::where('code', 'DCVIC')->first()?->id,
        \App\Models\Operator::where('code', 'HIESG')->first()?->id,
        \App\Models\Operator::where('code', 'IP')->first()?->id,
    ];
} else {
    $allowedOperatorIds = [$user->operator_id];
}
// Filter out nulls
$allowedOperatorIds = array_filter($allowedOperatorIds);

// Query records with operator filter
$records = VendRecord::with(['customer', 'operator'])
    ->where('date', $maxDate)
    ->whereNotNull('customer_id')
    ->whereIn('operator_id', $allowedOperatorIds)
    ->whereNotIn('vend_id', $testingVendIds)
    ->orderBy('vend_code')
    ->get();

echo "Count: " . $records->count() . "\n";

$list = $records->map(function ($r) {
    // Determine customer name: refer to logic in DashboardController or just use relationship
    // Dashboard usually relies on the record's relation.
    $customerName = $r->customer ? $r->customer->name : 'Unknown';
    $customerCode = $r->customer ? $r->customer->code : 'N/A';
    $operatorCode = $r->operator ? $r->operator->code : 'N/A';
    return "{$r->vend_code} | {$customerCode} | {$customerName} | {$operatorCode}";
})->implode("\n");

file_put_contents('oct_2025_active_vends.txt', "Vend Code | Customer Code | Customer Name | Operator Code\n" . "--------------------------------------------------------\n" . $list);

echo "List saved to oct_2025_active_vends.txt\n";
