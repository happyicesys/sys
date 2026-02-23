<?php

use App\Jobs\SendOperatorCallback;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/bootstrap/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = 'https://httpbin.org/post';
$data = ['test' => 'value', 'event' => 'verification'];

echo "Dispatching SendOperatorCallback job to $url...\n";

try {
    SendOperatorCallback::dispatch($url, $data)->onQueue('default');
    echo "Job dispatched successfully.\n";
    echo "Please check your queue worker or logs (if sync) to verify execution.\n";
    echo "You should see a request to https://httpbin.org/post.\n";
} catch (\Exception $e) {
    echo "Error dispatching job: " . $e->getMessage() . "\n";
}
