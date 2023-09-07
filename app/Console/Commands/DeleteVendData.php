<?php

namespace App\Console\Commands;

use App\Models\VendData;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteVendData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:vend-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete vending machine data';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        VendData::whereDate('created_at', '<=', Carbon::today()->subDays(2))->where('is_keep', false)->delete();
    }
}
