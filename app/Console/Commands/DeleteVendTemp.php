<?php

namespace App\Console\Commands;

use App\Models\VendTemp;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteVendTemp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:vend-temp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete vending machine temperature';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        VendTemp::whereDate('created_at', '<=', Carbon::today()->subDays(14))->delete();
    }
}
