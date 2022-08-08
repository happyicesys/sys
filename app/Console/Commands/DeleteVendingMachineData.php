<?php

namespace App\Console\Commands;

use App\Models\VendingMachineData;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteVendingMachineData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:vending-machine-data';

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
        VendingMachineData::whereDate('created_at', '<=', Carbon::today()->subDays(3))->delete();
    }
}
