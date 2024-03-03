<?php

namespace App\Console\Commands;

use App\Jobs\MoveVendTransactionToArchive;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ArchiveVendTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'archive:vend-transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vend Transaction Archive Command';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateBefore = Carbon::today()->subYears(1)->startOfDay();

        MoveVendTransactionToArchive::dispatch($dateBefore);
    }
}
