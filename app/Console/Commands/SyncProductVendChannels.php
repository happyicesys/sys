<?php

namespace App\Console\Commands;

use App\Jobs\SyncProductVendChannels as SyncProductVendChannelsJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncProductVendChannels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:product-vend-channels
                            {date? : Date in Y-m-d format. Defaults to today.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Snapshot active vend-channel counts per product for a given date';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $date = $this->argument('date') ?: Carbon::today()->toDateString();

        $this->info("Syncing product vend channels for {$date}…");

        SyncProductVendChannelsJob::dispatchSync($date);

        $this->info("Done.");
    }
}
