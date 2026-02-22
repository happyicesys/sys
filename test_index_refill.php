<?php
$request = new \Illuminate\Http\Request();
$request->merge([
    'numberPerPage' => 10,
    'sortKey' => 'date',
    'sortBy' => false,
    'date_from' => \Carbon\Carbon::today()->subDays(60)->startOfDay(),
    'date_to' => \Carbon\Carbon::now(),
    'is_get_data' => true
]);

auth()->login(\App\Models\User::first());
$response = app(\App\Http\Controllers\OpsJobController::class)->index($request);
$data = json_decode(substr(str_replace('&quot;', '"', $response->toResponse($request)->getContent()), strpos($response->toResponse($request)->getContent(), 'data-page=') + 11), true);
$jobs = $data['props']['opsJobs']['data'] ?? [];
echo "Jobs returned: " . count($jobs) . "\n";
foreach (array_slice($jobs, 0, 3) as $job) {
    echo "Job ID: {$job['id']}, Refillable Amount: " . $job['refillable_amount'] . "\n";
}
