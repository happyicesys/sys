<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVendData;
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
    public function handle()
    {
        $mqtt = MQTT::connection();
        $mqtt->subscribe('#', function (string $topic, string $message) {
            $processedMessage = json_decode($message, true);
            ProcessVendData::dispatch($processedMessage, '143.198.221.235');
        }, 1);
        $mqtt->loop(true);
    }
}
