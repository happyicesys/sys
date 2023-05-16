<?php

namespace App\Console\Commands;

use App\Jobs\RefundOmiseJob;
use Illuminate\Console\Command;

class RefundOmise extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refund:omise {orderId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refund feature by payment gateway Omise';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = $this->argument('orderId');

        if($orderId) {
            RefundOmiseJob::dispatch($orderId);
        } else {
            $this->error('Please provide order id');
        }

    }
}
