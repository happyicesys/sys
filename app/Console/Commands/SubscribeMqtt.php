<?php

namespace App\Console\Commands;

use App\Http\Controllers\VendDataController;
use App\Services\MqttService;
use Illuminate\Console\Command;
// use PhpMqtt\Client\Facades\MQTT;
use App\Models\VendData;

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
    protected $mqttService;

    public function __construct()
    {
        parent::__construct();
        $this->mqttService = new MqttService();
    }

    public function handle()
    {
        $this->mqttService->subscribe();
    }
}
