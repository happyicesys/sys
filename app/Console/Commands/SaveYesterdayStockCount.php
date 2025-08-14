<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vend;
use App\Jobs\SaveStockCount;
use Carbon\Carbon;

class SaveYesterdayStockCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save:yesterday-stock-count';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue SaveStockCount job for all active vends for yesterday\'s date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $yesterday = Carbon::yesterday()->toDateString();

        $vends = Vend::where('is_active', true)->has('customer')->pluck('id');

        $count = 0;
        foreach ($vends as $vendId) {
            SaveStockCount::dispatch($yesterday, $vendId); // queued job
            $count++;
        }
    }
}
