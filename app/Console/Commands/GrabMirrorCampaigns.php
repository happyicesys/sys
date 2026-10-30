<?php

namespace App\Console\Commands;

use App\Services\GrabCampaignMirror;
use Illuminate\Console\Command;

/**
 * Read every Grab store's ongoing and upcoming campaigns into the mirror (plan §9.3).
 * Read only on Grab. Every 10 minutes; ended rows older than 30 days are pruned.
 */
class GrabMirrorCampaigns extends Command
{
    protected $signature = 'grab:mirror-campaigns
                            {--store= : One merchantID only}';

    protected $description = 'Read what campaigns Grab has live per store (Grab Promotions mirror)';

    public function handle(GrabCampaignMirror $mirror): int
    {
        $counts = ['ok' => 0, 'refused' => 0, 'no_account' => 0, 'failed' => 0];
        $campaigns = 0;

        foreach ($mirror->stores($this->option('store')) as $ref) {
            $result = $mirror->refresh($ref);
            if ($result['result'] !== 'refused' && $result['result'] !== 'no_account') {
                usleep((int) config('grab.store_api_spacing_ms', 300) * 1000);
            }

            if ($result['result'] === 'rate_limited') {
                $this->warn('Stopped at Grab rate limit (429); the next run carries on.');
                break;
            }

            $counts[$result['result']]++;
            $campaigns += $result['ongoing'] + $result['upcoming'];
            if ($result['result'] === 'failed') {
                $this->line("{$ref->ref_number}: Grab answered ".($result['code'] ?? 'nothing'));
            }
        }

        $pruned = $mirror->prune();

        $this->info("Stores read: {$counts['ok']}   campaigns on Grab: {$campaigns}   failed: {$counts['failed']}   skipped (refused / no account): {$counts['refused']} / {$counts['no_account']}   ended rows pruned: {$pruned}");

        return self::SUCCESS;
    }
}
