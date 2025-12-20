<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixCampaignLabels extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vend:fix-campaign-labels {--days=30 : How many days back to look}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix label_json based on campaign_label_pivot using dynamic campaign lookup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dateFrom = \Carbon\Carbon::now()->subDays($days)->startOfDay();

        $this->info("Looking for transactions starting from: " . $dateFrom->toDateTimeString());

        // Find range
        $minId = \App\Models\VendTransaction::where('created_at', '>=', $dateFrom)->min('id');
        $maxId = \App\Models\VendTransaction::max('id');

        if (!$minId || !$maxId) {
            $this->info("No transactions found.");
            return;
        }

        $this->info("Dispatching jobs for ID range: $minId to $maxId");

        $chunkSize = 1000;
        $totalJobs = ceil(($maxId - $minId) / $chunkSize);
        $bar = $this->output->createProgressBar($totalJobs);

        for ($currentId = $minId; $currentId <= $maxId; $currentId += $chunkSize) {
            $endId = min($currentId + $chunkSize - 1, $maxId);

            \App\Jobs\FixCampaignLabelsJob::dispatch($currentId, $endId);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done! Dispatched $totalJobs jobs to the 'low' queue.");
    }
}