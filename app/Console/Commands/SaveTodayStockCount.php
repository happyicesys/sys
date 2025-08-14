<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vend;
use App\Jobs\SaveStockCount;
use Carbon\Carbon;

class SaveTodayStockCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save:today-stock-count';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue SaveStockCount job for all active vends for today\'s date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today()->toDateString();

        $vends = Vend::where('is_active', true)->has('customer')->pluck('id');

        $count = 0;
        foreach ($vends as $vendId) {
            SaveStockCount::dispatch($today, $vendId); // queued job
            $count++;
        }
    }
}
