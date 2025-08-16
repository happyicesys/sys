<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vend;
use App\Jobs\SaveStockCount;
use Carbon\Carbon;

class SaveStockCountWithDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save:stock-count {date : The date in Y-m-d format (e.g. 2025-08-16)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue SaveStockCount job for all active vends for the given date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateInput = $this->argument('date');

        try {
            $date = Carbon::parse($dateInput)->toDateString();
        } catch (\Exception $e) {
            $this->error("Invalid date format. Please use Y-m-d (e.g. 2025-08-16).");
            return;
        }

        $vends = Vend::where('is_active', true)->has('customer')->pluck('id');

        $count = 0;
        foreach ($vends as $vendId) {
            SaveStockCount::dispatch($date, $vendId);
            $count++;
        }

        $this->info("Dispatched {$count} SaveStockCount jobs for date {$date}.");
    }
}
