<?php
$request = new \Illuminate\Http\Request();
$request->merge(['is_get_data' => true]);
// mock auth user
$user = \App\Models\User::first();
auth()->login($user);

$response = app(\App\Http\Controllers\OpsJobController::class)->edit($request, 1468);
$items = collect($response->original['opsJobItems']);
foreach($items as $idx => $item) {
    echo "Item {$item->id} Refillable Amount: " . $item->refillable_amount . "\n";
    if ($idx >= 3) break;
}
