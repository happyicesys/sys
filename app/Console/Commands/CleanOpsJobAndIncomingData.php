<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\CleanOpsJobAndIncomingSeeder;

class CleanOpsJobAndIncomingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:ops-job-and-incoming-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean ops job and incoming data by running CleanOpsJobAndIncomingSeeder';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting cleaning of ops job and incoming data...');

        $seeder = new CleanOpsJobAndIncomingSeeder();
        $seeder->run();

        $this->info('Ops job and incoming data cleaned successfully.');

        return 0;
    }
}
