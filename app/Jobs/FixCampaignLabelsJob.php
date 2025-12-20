<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\VendTransaction;
use App\Models\Campaign;

class FixCampaignLabelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $startId;
    public $endId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($startId, $endId)
    {
        $this->startId = $startId;
        $this->endId = $endId;
        $this->onQueue('low');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $transactions = VendTransaction::where('id', '>=', $this->startId)
            ->where('id', '<=', $this->endId)
            ->get();

        foreach ($transactions as $transaction) {
            // Cast to array to safely access keys
            $json = (array) $transaction->vend_transaction_json;

            // Check if pivot exists
            if (
                isset($json['campaign_label_pivot']) && is_array($json['campaign_label_pivot']) &&
                !empty($json['campaign_label_pivot'])
            ) {

                $pivotIds = $json['campaign_label_pivot'];

                // Resolve Campaigns
                $campaigns = Campaign::whereIn('id', $pivotIds)->get();
                $labels = [];

                foreach ($campaigns as $campaign) {
                    if ($campaign->slug) {
                        $labels[] = $campaign->slug . '(' . $campaign->id . ')';
                    }
                }

                if (!empty($labels) && $transaction->label_json !== $labels) {
                    $transaction->label_json = $labels;
                    $transaction->save();
                }
            }
        }
    }
}
