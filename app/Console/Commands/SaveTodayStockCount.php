<?php

namespace App\Console\Commands;

use App\Jobs\SaveStockCount;
use App\Models\Vend;
use App\Services\Stock\StockCountWarehouseQty;
use Carbon\Carbon;
use Illuminate\Console\Command;

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

        // One CMS fetch for the whole run; the jobs read it from the cache.
        if (! app(StockCountWarehouseQty::class)->prime($today)) {
            $this->warn('CMS warehouse qty unreachable; jobs will retry it, else save machine qty only.');
        }

        $vends = Vend::where('is_active', true)->has('customer')->pluck('id');

        $count = 0;
        foreach ($vends as $vendId) {
            SaveStockCount::dispatch($today, $vendId); // queued job
            $count++;
        }
    }
}
