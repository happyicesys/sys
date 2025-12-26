$service = new \App\Services\MachineHealthDashboardService();
$request = new \Illuminate\Http\Request();

// 1. Run without SKU filter
echo "--- Run 1: No SKU Filter ---\n";
$data1 = $service->getDashboardData($request);
$tempCount1 = count($data1['temperature']['rising_lowest']['rows'] ?? [])
+ count($data1['temperature']['worst_minima']['rows'] ?? [])
+ count($data1['temperature']['not_reaching_threshold']['rows'] ?? []);
$connCount1 = count($data1['connectivity']['primary'] ?? []);
echo "Temperature Rows: $tempCount1\n";
echo "Connectivity Rows: $connCount1\n";

// 2. Run with SKU filter (use a dummy SKU that likely yields 0 results)
echo "\n--- Run 2: With SKU Filter 'NON_EXISTENT_SKU' ---\n";
$request->merge(['channel_sku' => 'NON_EXISTENT_SKU_99999']);
$data2 = $service->getDashboardData($request);
$tempCount2 = count($data2['temperature']['rising_lowest']['rows'] ?? [])
+ count($data2['temperature']['worst_minima']['rows'] ?? [])
+ count($data2['temperature']['not_reaching_threshold']['rows'] ?? []);
$connCount2 = count($data2['connectivity']['primary'] ?? []);
echo "Temperature Rows: $tempCount2\n";
echo "Connectivity Rows: $connCount2\n";

if ($tempCount1 > 0 && $tempCount2 == $tempCount1) {
echo "\n[FAIL] Temperature metrics did not change with SKU filter (Expected 0 or fewer).\n";
}
if ($connCount1 > 0 && $connCount2 == $connCount1) {
echo "\n[FAIL] Connectivity metrics did not change with SKU filter (Expected 0 or fewer).\n";
}