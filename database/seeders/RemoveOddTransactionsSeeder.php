<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Jobs\RemoveOddTransactions;

class RemoveOddTransactionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Force the env var to true for this execution context
        // This ensures the job runs even if the .env file has it set to false
        putenv('DELETE_ODD_TRANSACTIONS=true');

        // Check for environment variables or prompt/default
        $defaultFrom = '2025-11-01';
        $defaultTo = '2026-01-31';

        $from = env('START_DATE') ?? $this->command->ask('Enter start date (YYYY-MM-DD)', $defaultFrom);
        $to = env('END_DATE') ?? $this->command->ask('Enter end date (YYYY-MM-DD)', $defaultTo);

        $this->command->info("Dispatching RemoveOddTransactions job from $from to $to...");

        RemoveOddTransactions::dispatchSync($from, $to);

        $this->command->info('Job dispatched successfully.');
    }
}
