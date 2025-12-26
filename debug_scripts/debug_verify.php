$service = new \App\Services\MachineHealthDashboardService();
$request = new \Illuminate\Http\Request();
$request->merge(['channel_sku' => 'NON_EXISTENT_SKU_99999']);

echo "Checking getDashboardData with SKU filter...\n";
$data = $service->getDashboardData($request);

echo "Checking connectivity count...\n";
echo "Connectivity: " . count($data['connectivity']['primary']) . "\n";