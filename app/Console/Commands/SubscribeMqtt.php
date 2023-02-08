<?php

namespace App\Console\Commands;

use App\Http\Controllers\VendDataController;
use App\Jobs\ProcessVendData;
use App\Services\VendDataService;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;

class SubscribeMqtt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscribe:mqtt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe mqtt at mqtt.happyice.net';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected $vendDataService;

    public function __construct(VendDataService $vendDataService)
    {
        parent::__construct();
        $this->vendDataService = $vendDataService;
    }

    public function handle()
    {
        $mqtt = MQTT::connection();
        $mqtt->subscribe('#', function (string $topic, string $message) {
            $vendDataService = $this->vendDataService->getVendData($message, '143.198.221.235', 'mqtt');
            // echo sprintf('Response %s', implode($vendDataService->toArray()));
            // $obj = new VendDataController($this->vendDataService->getVendData($message, '143.198.221.235', 'mqtt'));
            echo sprintf('Received message on topic [%s]: %s', $topic, $message);
            ProcessVendData::dispatch($message, '143.198.221.235', 'mqtt');
        }, 1);
        $mqtt->loop(true);
    }
}
