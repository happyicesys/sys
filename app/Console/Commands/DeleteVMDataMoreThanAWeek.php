<?php

namespace App\Console\Commands;

use App\Models\VMData;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteVMDataMoreThanAWeek extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:vm-data-more-than-a-week';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete VM Data More Than a Week';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        VMData::whereDate('created_at', '<=', Carbon::today()->subWeeks(1))->delete();
    }
}
