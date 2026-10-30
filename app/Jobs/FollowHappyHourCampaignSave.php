<?php

namespace App\Jobs;

use App\Models\HappyHourCampaign;
use App\Models\Scopes\OperatorHappyHourCampaignScope;
use App\Services\HappyHour\HappyHourPlanner;
use App\Services\HappyHour\HappyHourWelcomeFallback;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * What a Happy Hour campaign save or delete sets off, run right after the page has its response
 * (`dispatchAfterResponse`; not queued) and holding the planner lock, so it never interleaves with the
 * minute run (audit 2026-10-30: the two wrote slots together, and a fleet-wide re-plan held the save
 * request for ~26 s):
 *  1. takes back the campaign's slots, all of them (a schedule change, a delete) or those of the machines
 *     it gave up — again under the lock, so a slot a run in flight wrote from the old settings goes too;
 *  2. group campaigns follow (a machine picked by name leaves them, or rejoins after a delete);
 *  3. plans the campaign's machines at once instead of at the next minute;
 *  4. machines with no UI Setting re-read /parameters, so the bear comes on or goes off now.
 */
class FollowHappyHourCampaignSave
{
    use Dispatchable;

    /**
     * @param  'all'|list<int>|null  $cancel  every machine's slots, these machines' slots, or none
     * @param  list<int>  $membersBefore  the campaign's machines before the save
     */
    public function __construct(
        public readonly int $campaignId,
        public readonly string|array|null $cancel,
        public readonly array $membersBefore = [],
    ) {}

    public function handle(HappyHourPlanner $planner): void
    {
        try {
            $planner->exclusively(fn () => $this->follow($planner), 120);
        } catch (LockTimeoutException) {
            // Never lose a cancel: a stuck lock (its holder died) expires on its own, but this save's
            // slots must change now.
            Log::warning('happy hour save followed without the planner lock', ['campaign' => $this->campaignId]);
            $this->follow($planner);
        }
    }

    private function follow(HappyHourPlanner $planner): void
    {
        $now = Carbon::now();
        $campaign = HappyHourCampaign::withTrashed()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->find($this->campaignId);
        if ($campaign === null) {
            return;
        }
        if ($this->cancel !== null && $this->cancel !== []) {
            $planner->cancel($campaign, $now, $this->cancel === 'all' ? null : $this->cancel);
        }
        $planner->followGroups($now);
        try {
            $planner->planCampaign($campaign->fresh() ?? $campaign, $now);
        } catch (\Throwable $e) {
            // The campaign is saved; the minute run plans it instead.
            report($e);
        }
        $after = DB::table('happy_hour_campaign_vend')->where('happy_hour_campaign_id', $campaign->id)
            ->pluck('vend_id')->map(fn ($id) => (int) $id)->all();
        HappyHourWelcomeFallback::nudgeUnbound([...$this->membersBefore, ...$after]);
    }
}
