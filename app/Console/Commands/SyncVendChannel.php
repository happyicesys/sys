<?php

namespace App\Console\Commands;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\Vend;
use Illuminate\Console\Command;

class SyncVendChannel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:vend-channel-json {vendCode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sync vend channel json by id';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $vendCode = $this->argument('vendCode');
        $vend = Vend::where('code', $vendCode)->first();

        if($vend) {
            SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
        }
    }
}
