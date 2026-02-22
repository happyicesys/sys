<?php
$request = new \Illuminate\Http\Request();
$request->merge(['is_get_data' => true]);
$user = \App\Models\User::first();
auth()->login($user);

$response = app(\App\Http\Controllers\OpsJobController::class)->edit($request, 1468);
$content = json_decode(substr(str_replace('&quot;', '"', $response->toResponse($request)->getContent()), strpos($response->toResponse($request)->getContent(), 'data-page=') + 11), true);
$items = $content['props']['opsJob'] ?? [];
echo "Item Refillable Amounts:\n";
foreach(array_slice($items['opsJobItems'] ?? [], 0, 5) as $item) {
    echo "Item {$item['id']} Refillable Amount: " . $item['refillable_amount'] . "\n";
}
