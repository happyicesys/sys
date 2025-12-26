<?php

$checkDateFrom = '2025-12-20';
$customers = App\Models\Customer::where('name', 'like', '%zoo%')->pluck('id');

$records = App\Models\VendRecord::whereIn('customer_id', $customers)
    ->where('date', '>=', $checkDateFrom)
    ->get();

echo "Checking " . $records->count() . " records since $checkDateFrom\n";

if ($records->count() > 0) {
    foreach ($records->take(5) as $r) {
        echo "ID: {$r->id} | Date: {$r->date} | Year: {$r->year} | Month: {$r->month} | Day: {$r->day}\n";
    }
}
