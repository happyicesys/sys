<?php
$request = new \Illuminate\Http\Request();
$request->merge(['is_get_data' => true]);
// mock auth user
$user = \App\Models\User::first();
auth()->login($user);

$response = app(\App\Http\Controllers\OpsJobController::class)->edit($request, 1468);
$opsJob = $response->original['opsJob'];
$items = collect(json_decode(json_encode($opsJob), true)['opsJobItems']);
foreach ($items->take(5) as $item) {
    echo "Item {$item['id']} Refillable Amount: " . $item['refillable_amount'] . "\n";
}
