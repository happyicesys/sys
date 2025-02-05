<?php

namespace App\Console\Commands;

use App\Models\OperatorPaymentGateway;
use Illuminate\Console\Command;


class UpdateOpnKeyDCVend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:opn-key-dcvend';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $data = OperatorPaymentGateway::where('operator_id', 34)->first();
        $data->key1 = 'pkey_62mdypoi6t958cqlknk';
        $data->key2 = 'skey_62me0do5got229iesv0';
        $data->save();
    }
}
