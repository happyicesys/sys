<?php

namespace App\Console\Commands;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\Vend;
use Illuminate\Console\Command;

class SyncAllVendChannels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:all-vend-channel-json';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sync all vend channel json';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $vends = Vend::all();

        foreach($vends as $vend) {
            SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
        }
    }
}
