$service = new \App\Services\MachineHealthDashboardService();
$request = new \Illuminate\Http\Request();
try {
$data = $service->getDashboardData($request);
echo "Successfully retrieved dashboard data.\n";
print_r($data['no_transactions']);
} catch (\Exception $e) {
echo "Error: " . $e->getMessage() . "\n";
echo $e->getTraceAsString();
}