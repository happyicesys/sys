<?php
$user = App\Models\User::where('name', 'like', '%Brian%')->first();
if (!$user) {
    echo "User Brian not found. Listing first 5 users:\n";
    foreach (App\Models\User::take(5)->get() as $u) {
        echo $u->id . ": " . $u->name . "\n";
    }
} else {
    echo "User found: " . $user->name . " (ID: " . $user->id . ")\n";
    echo "Operator ID: " . $user->operator_id . "\n";

    $vendsCount = $user->vends()->count();
    echo "Assigned Vends Count: " . $vendsCount . "\n";

    if ($vendsCount > 0) {
        // Check if Zoo vends are in the assigned list
        $zooCustomerIds = App\Models\Customer::where('name', 'like', '%zoo%')->pluck('id');
        $zooVends = App\Models\Vend::whereIn('customer_id', $zooCustomerIds)->pluck('id');

        $assignedZooVends = $user->vends()->whereIn('vends.id', $zooVends)->pluck('vends.id');

        echo "Total Zoo Vends: " . $zooVends->count() . "\n";
        echo "Assigned Zoo Vends: " . $assignedZooVends->count() . "\n";

        if ($zooVends->count() > $assignedZooVends->count()) {
            echo "WARNING: User is restricted to a subset of Zoo vends.\n";
            $missing = $zooVends->diff($assignedZooVends);
            echo "Missing Vend IDs: " . implode(', ', $missing->toArray()) . "\n";
        }
    } else {
        echo "User has NO assigned vends (should see all, subject to Operator check).\n";
    }
}
