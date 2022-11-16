<?php

namespace App\Console\Commands;

use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncVendOnlineStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:vend-online-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduler check online status (more than 5 mins last updated time becomes offline)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $vends = Vend::all();

        foreach($vends as $vend) {
            $vend->is_online = true;
            if($vend->last_updated_at and $vend->last_updated_at->addMinutes(5)->isPast()) {
                $vend->is_online = false;
            }
            $vend->save();
        }
    }
}
