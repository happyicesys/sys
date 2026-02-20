<?php

namespace App\Console\Commands;

use App\Jobs\StoreVendsRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

class SeedVendRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:vend-records {dateFrom} {dateTo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed past missing vend_records data for a specific date range (Format: YYYY-MM-DD)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $from = $this->argument('dateFrom');
        $to = $this->argument('dateTo');

        try {
            $startDate = Carbon::parse($from);
            $endDate = Carbon::parse($to);
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use YYYY-MM-DD.');
            return Command::FAILURE;
        }

        if ($startDate->gt($endDate)) {
            $this->error('The starting date must be before or equal to the ending date.');
            return Command::FAILURE;
        }

        $this->info("Starting to seed vend records from {$startDate->toDateString()} to {$endDate->toDateString()}...");

        $period = CarbonPeriod::create($startDate, $endDate);

        $bar = $this->output->createProgressBar(count($period));
        $bar->start();

        foreach ($period as $date) {
            $dateString = $date->toDateString();

            // We dispatch synchronously so it completes immediately rather than pushing to a background queue
            dispatch_sync(new StoreVendsRecord($dateString, $dateString, true));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Vend records seeded successfully!');

        return Command::SUCCESS;
    }
}
