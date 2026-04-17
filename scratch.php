<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \Illuminate\Http\Request::create('/api/machine-health', 'GET', [
    'machine_limit' => 5,
    'temperature_window_days' => 5
]);

$service = app(\App\Services\MachineHealthDashboardService::class);
try {
    $data = $service->getDashboardData($request);
    echo "Success! \n";
    echo "Keys in no_transactions: " . implode(", ", array_keys($data['no_transactions'])) . "\n";
    echo "Any sales count: " . count($data['no_transactions']['any_sales']) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
