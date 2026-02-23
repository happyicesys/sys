<?php

use App\Models\Vend;
use App\Models\VendRecord;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

ini_set('memory_limit', '512M');

echo "Starting VendRecords history fix...\n";

// Fetch all vends with their movement history
$vends = Vend::whereNotNull('customer_movement_history_json')
    ->get(['id', 'code', 'customer_movement_history_json']);

echo "Found " . $vends->count() . " vends with movement history.\n";

$updatedCount = 0;

foreach ($vends as $vend) {
    $history = $vend->customer_movement_history_json;
    if (empty($history) || !is_array($history)) {
        continue;
    }

    // Sort history by date just in case
    usort($history, function ($a, $b) {
        return strcmp($a['created_at'], $b['created_at']);
    });

    // Build timeline
    // Timeline is a list of segments: [start_date, end_date, customer_id]
    $timeline = [];
    $currentBinding = null;
    $lastDate = null;

    foreach ($history as $entry) {
        $date = Carbon::parse($entry['created_at']);
        $isBinding = isset($entry['is_binding']) ? $entry['is_binding'] : true; // Assume true if missing (older records might differ)

        // Refine is_binding logic:
        // Usually creation of a movement log implies a change.
        // If entry has 'is_binding' key, use it.
        // Based on previous JSON, 'is_binding': true means Bind. false means Unbind?
        // Let's assume standard logic:
        // If it binds to a customer, it's a binding event.
        // If it unbinds, it might have null customer or specific flag.

        // In the JSON seen earlier:
        // id 5865 (Fourth Avenue), is_binding: false, created: Feb 18
        // id 6380 (198Test), is_binding: true, created: Mar 21
        // This implies Feb 18 was an Unbind event (from 5865?).

        if ($currentBinding) {
            // Close previous segment
            $timeline[] = [
                'start' => $lastDate,
                'end' => $date,
                'customer_id' => $currentBinding['customer_id']
            ];
        }

        if ($isBinding) {
            $currentBinding = ['customer_id' => $entry['id']]; // entry['id'] is customer id
            $lastDate = $date;
        } else {
            // Unbind event
            $currentBinding = null;
            $lastDate = $date;
        }
    }

    // Add final open-ended segment if currently bound
    if ($currentBinding) {
        $timeline[] = [
            'start' => $lastDate,
            'end' => Carbon::now()->addYear(), // Future
            'customer_id' => $currentBinding['customer_id']
        ];
    }

    if (empty($timeline)) {
        continue;
    }

    // Now update VendRecords
    // We only need to check records that might be wrong.
    // Ideally we iterate all records for this vend.

    $records = VendRecord::where('vend_id', $vend->id)->get();

    foreach ($records as $record) {
        $recordDate = Carbon::parse($record->date);

        // Find matching segment
        $activeCustomerId = null;
        foreach ($timeline as $segment) {
            if ($recordDate->gte($segment['start']) && $recordDate->lt($segment['end'])) {
                $activeCustomerId = $segment['customer_id'];
                break;
            }
        }

        // Determine correct operator for the customer
        // We need to fetch customer to get operator_id
        // To optimize, we could cache customer operator IDs.

        $shouldUpdate = false;

        if ($activeCustomerId != $record->customer_id) {
            $shouldUpdate = true;
        }

        if ($shouldUpdate) {
            // If activeCustomerId is null, we Unbind
            if (is_null($activeCustomerId)) {
                $record->customer_id = null;
                // Operator likely remains unchanged or set to owner?
                // Usually vend record operator reflects the machine operator.
                // But in this system, vend_records.operator_id is often the customer's operator.
                // Let's check logic: if binded, use customer's operator. If unbinded, keep existing or set to vend's owner?
                // Current logic usually sets operator based on binding.
                // For now, let's only update customer_id to null. Operator ID logic is complex.
                // But wait, graph filters by operator_id. If we keep old operator_id, it might still show up?
                // If customer_id is null, graph filter `whereNotNull('customer_id')` EXCLUDES it.
                // So setting customer_id = null is sufficient to hide it from "Active" graph.
            } else {
                $record->customer_id = $activeCustomerId;

                // Fetch customer operator
                $customer = Customer::find($activeCustomerId);
                if ($customer) {
                    $record->operator_id = $customer->operator_id;
                }
            }

            $record->save();
            $updatedCount++;
        }
    }
}

echo "Fix complete. Updated {$updatedCount} records.\n";
