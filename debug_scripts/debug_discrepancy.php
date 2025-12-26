<?php

// 1. Check Global Scope
$hasScope = App\Models\VendRecord::hasGlobalScope(App\Models\Scopes\OperatorVendRecordScope::class);
echo "OperatorVendRecordScope Active: " . ($hasScope ? 'Yes' : 'No') . "\n";
echo "Auth Check: " . (auth()->check() ? 'Logged In' : 'Guest') . "\n";

// 2. Compare Customers
$sqlCustomers = DB::select("SELECT id FROM customers WHERE name LIKE '%zoo%' OR virtual_customer_prefix LIKE '%zoo%' OR virtual_customer_code LIKE '%zoo%'");
$sqlIds = array_map(fn($r) => $r->id, $sqlCustomers);
sort($sqlIds);

$eloquentCustomers = App\Models\Customer::where('name', 'like', '%zoo%')
    ->orWhere('virtual_customer_prefix', 'like', '%zoo%')
    ->orWhere('virtual_customer_code', 'like', '%zoo%')
    ->pluck('id')
    ->toArray();
sort($eloquentCustomers);

echo "SQL Customers Count: " . count($sqlIds) . "\n";
echo "Eloquent Customers Count: " . count($eloquentCustomers) . "\n";

$diff = array_diff($sqlIds, $eloquentCustomers);
if (!empty($diff)) {
    echo "Customers found in SQL but not Eloquent: " . implode(', ', $diff) . "\n";
} else {
    echo "Customer lists match.\n";
}

// 3. Compare Records for Dec 20-25 2025
$checkDateFrom = '2025-12-20';
$checkDateTo = '2025-12-25';

$sqlSales = DB::select("
    SELECT SUM(total_amount)/100 as total
    FROM vend_records
    WHERE customer_id IN (" . implode(',', $sqlIds) . ")
    AND date BETWEEN ? AND ?
", [$checkDateFrom, $checkDateTo]);

echo "SQL Sales (Dec 20-25): " . number_format($sqlSales[0]->total ?? 0, 2) . "\n";

$eloquentSales = App\Models\VendRecord::whereIn('customer_id', $eloquentCustomers)
    ->whereBetween('date', [$checkDateFrom, $checkDateTo])
    ->sum('total_amount') / 100;

echo "Eloquent Sales (Dec 20-25): " . number_format($eloquentSales, 2) . "\n";

// 4. If Eloquent is 0, check specific record existence
if ($eloquentSales == 0 && $sqlSales[0]->total > 0) {
    echo "Investigating missing records in Eloquent...\n";
    // Grab a raw record ID from SQL
    $sample = DB::select("SELECT id, customer_id, vend_id, date FROM vend_records WHERE date = '2025-12-20' AND customer_id IN (" . implode(',', $sqlIds) . ") LIMIT 1");
    if (!empty($sample)) {
        $id = $sample[0]->id;
        echo "Sample Record ID: $id (Customer: {$sample[0]->customer_id}, Vend: {$sample[0]->vend_id})\n";

        $rec = App\Models\VendRecord::find($id);
        if ($rec) {
            echo "Eloquent found the record explicitly!\n";
        } else {
            echo "Eloquent could NOT find the record explicitly via find($id)!\n";
            // Check without scopes using DB facade but simulating model? No, just model without scopes
            $recNoScope = App\Models\VendRecord::withoutGlobalScopes()->find($id);
            if ($recNoScope) {
                echo "Eloquent found it WITHOUT Global Scopes.\n";
            } else {
                echo "Eloquent could not find it even without global scopes (very strange).\n";
            }
        }
    }
}
