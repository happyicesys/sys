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

        $this->info("Looking for transactions with 'campaign_label_pivot' starting from: " . $dateFrom->toDateTimeString());

        $query = \App\Models\VendTransaction::query()
            ->where('created_at', '>=', $dateFrom)
            ->whereNotNull('vend_transaction_json');

        // Note: JSON where clauses can be slow, but since we have a date filter, it satisfies the index usage.
// We will filter in PHP to avoid "whereJsonContains" slowness if the index is missing.

        $totalProcessed = 0;
        $totalUpdated = 0;

        $this->output->progressStart($query->count());

        $query->chunkById(500, function ($transactions) use (&$totalProcessed, &$totalUpdated) {
            foreach ($transactions as $transaction) {
                // Cast to array to safely access keys, avoiding "Cannot use object of type json as array"
                $json = (array) $transaction->vend_transaction_json;

                // Check if pivot exists
                if (
                    isset($json['campaign_label_pivot']) && is_array($json['campaign_label_pivot']) &&
                    !empty($json['campaign_label_pivot'])
                ) {

                    $pivotIds = $json['campaign_label_pivot'];

                    // Resolve Campaigns
                    $campaigns = \App\Models\Campaign::whereIn('id', $pivotIds)->get();
                    $labels = [];

                    foreach ($campaigns as $campaign) {
                        if ($campaign->slug) {
                            $labels[] = $campaign->slug . '(' . $campaign->id . ')';
                        }
                    }

                    if (!empty($labels) && $transaction->label_json !== $labels) {
                        $transaction->label_json = $labels;
                        $transaction->save();
                        $totalUpdated++;
                    }
                }

                $totalProcessed++;
                $this->output->progressAdvance();
            }
        });

        $this->output->progressFinish();
        $this->info("Done! Processed: $totalProcessed, Updated: $totalUpdated");
    }
}