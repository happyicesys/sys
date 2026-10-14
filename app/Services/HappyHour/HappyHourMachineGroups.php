<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourCampaign;
use App\Models\Scopes\OperatorHappyHourCampaignScope;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use Illuminate\Support\Facades\DB;

/**
 * Live machine groups on a Happy Hour campaign (Brian, 2026-10-14): "All VM - Big Board" and "All
 * Smart Freezers" instead of binding each machine by hand.
 *
 * A group is LIVE: its members are the campaign OWNER's operator's active machines of that kind that
 * can run Happy Hour now (HappyHourEligibility) — never a sibling operator's (Brian: HIMD, UL-ST…
 * are not discounted by an HIPL campaign). A big board joins by itself when it reaches app 315, and
 * leaves when it is deactivated or moves operator.
 *
 * Members are materialised into `happy_hour_campaign_vend` with `via_group` set, so the planner, the
 * overlap check, /thumbnails and the bear fallback read one pivot as before. This class is the only
 * writer of the pivot: `apply()` sets hand-picked rows (`via_group` NULL) + current group members,
 * called on every campaign save and, for active campaigns with a group, by the minute planner.
 *
 * Picked by name beats a group (Brian, 2026-10-14): a machine another campaign IN FORCE (on, inside
 * its dates) picked by hand leaves the group for as long as that campaign is in force — the whole
 * machine, whatever the hours — and comes back when it is paused, ended or deleted. Otherwise a
 * machine another active campaign holds at overlapping times is skipped (first come keeps it).
 */
final class HappyHourMachineGroups
{
    public const VENDING_BIG_BOARD = 'vending_big_board';

    public const SMART_FREEZER = 'smart_freezer';

    public const GROUPS = [
        self::VENDING_BIG_BOARD => 'All VM - Big Board',
        self::SMART_FREEZER => 'All Smart Freezers',
    ];

    public static function label(string $group): string
    {
        return self::GROUPS[$group] ?? $group;
    }

    /**
     * Sets the campaign's machines: these hand-picked ones (null = keep the current hand-picked rows)
     * plus the live members of its groups.
     *
     * @param  list<int>|null  $handIds
     * @return array{added: list<int>, removed: list<int>}
     */
    public function apply(HappyHourCampaign $campaign, ?array $handIds = null): array
    {
        $current = DB::table('happy_hour_campaign_vend')->where('happy_hour_campaign_id', $campaign->id)
            ->pluck('via_group', 'vend_id')->mapWithKeys(fn ($group, $id) => [(int) $id => $group])->all();
        $hand = array_values(array_unique(array_map('intval', $handIds
            ?? array_keys(array_filter($current, fn ($group) => $group === null)))));

        $members = array_diff_key($this->members($campaign), array_flip($hand), $this->pickedByNameElsewhere($campaign));
        // A machine joining now must not already run another campaign at overlapping times.
        $joining = array_values(array_diff(array_keys($members), array_keys($current)));
        foreach (HappyHourOverlap::takenVendIds($this->schedule($campaign), $joining, $campaign->id) as $taken) {
            unset($members[$taken]);
        }

        $want = array_fill_keys($hand, null) + $members;
        $now = now();
        DB::transaction(function () use ($campaign, $current, $want, $now) {
            $gone = array_keys(array_diff_key($current, $want));
            if ($gone !== []) {
                DB::table('happy_hour_campaign_vend')->where('happy_hour_campaign_id', $campaign->id)->whereIn('vend_id', $gone)->delete();
            }
            foreach ($want as $vendId => $group) {
                if (! array_key_exists($vendId, $current)) {
                    // OrIgnore: a save and the minute run may add the same member at once (unique pair).
                    DB::table('happy_hour_campaign_vend')->insertOrIgnore([
                        'happy_hour_campaign_id' => $campaign->id, 'vend_id' => $vendId, 'via_group' => $group,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                } elseif ($current[$vendId] !== $group) {
                    DB::table('happy_hour_campaign_vend')->where('happy_hour_campaign_id', $campaign->id)->where('vend_id', $vendId)
                        ->update(['via_group' => $group, 'updated_at' => $now]);
                }
            }
        });

        return [
            'added' => array_values(array_diff(array_keys($want), array_keys($current))),
            'removed' => array_values(array_diff(array_keys($current), array_keys($want))),
        ];
    }

    /**
     * The minute run: every active campaign with a group follows its members.
     *
     * @return array<int, array{added: list<int>, removed: list<int>}> keyed by campaign id, changed ones only
     */
    public function syncActive(): array
    {
        $changes = [];
        HappyHourCampaign::query()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->active()
            ->whereNotNull('machine_groups')
            ->get()
            ->each(function (HappyHourCampaign $campaign) use (&$changes) {
                $result = $this->apply($campaign);
                if ($result['added'] !== [] || $result['removed'] !== []) {
                    $changes[$campaign->id] = $result;
                }
            });

        return $changes;
    }

    /**
     * The live members of the campaign's groups, as vend id => group.
     *
     * @return array<int, string>
     */
    public function members(HappyHourCampaign $campaign): array
    {
        return $this->membersFor((array) $campaign->machine_groups, (int) ($campaign->operator_id ?: OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID));
    }

    /**
     * @param  list<string>  $groups
     * @return array<int, string> vend id => group
     */
    public function membersFor(array $groups, int $operatorId): array
    {
        $groups = array_values(array_intersect($groups, array_keys(self::GROUPS)));
        if ($groups === []) {
            return [];
        }
        $types = array_map(fn ($g) => $g === self::SMART_FREEZER ? Vend::MACHINE_TYPE_SMART_FREEZER : Vend::MACHINE_TYPE_VENDING_MACHINE, $groups);

        $members = [];
        Vend::withoutGlobalScopes()
            ->where('operator_id', $operatorId)
            ->where('is_active', 1)
            ->whereIn('machine_type', $types)
            ->get(['id', 'machine_type', 'is_active', 'apk_version_code', 'apk_ver_json'])
            ->each(function (Vend $vend) use ($groups, &$members) {
                $group = $vend->machine_type === Vend::MACHINE_TYPE_SMART_FREEZER ? self::SMART_FREEZER : self::VENDING_BIG_BOARD;
                // Eligibility keeps out small boards and apps too old to price a promo.
                if (in_array($group, $groups, true) && HappyHourEligibility::supports($vend)) {
                    $members[(int) $vend->id] = $group;
                }
            });

        return $members;
    }

    /**
     * Machines another campaign in force today picked by hand — they outrank this campaign's groups.
     *
     * @return array<int, string> vend id => that campaign's name
     */
    public function pickedByNameElsewhere(HappyHourCampaign $campaign): array
    {
        if ((array) $campaign->machine_groups === []) {
            return [];
        }
        $inForce = HappyHourCampaign::query()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)
            ->inForce(now())->whereKeyNot($campaign->id)->pluck('name', 'id');
        if ($inForce->isEmpty()) {
            return [];
        }

        return DB::table('happy_hour_campaign_vend')
            ->whereIn('happy_hour_campaign_id', $inForce->keys())
            ->whereNull('via_group')
            ->get(['vend_id', 'happy_hour_campaign_id'])
            ->mapWithKeys(fn ($row) => [(int) $row->vend_id => $inForce[$row->happy_hour_campaign_id]])
            ->all();
    }

    /**
     * For the page: the group members this campaign gives up to a campaign that picked them by name.
     *
     * @return array<int, string> vend id => that campaign's name
     */
    public function overridden(HappyHourCampaign $campaign): array
    {
        return array_intersect_key($this->pickedByNameElsewhere($campaign), $this->members($campaign));
    }

    /** The campaign's schedule in the shape HappyHourOverlap compares. @return array<string, mixed> */
    private function schedule(HappyHourCampaign $campaign): array
    {
        return [
            'days_mask' => $campaign->days_mask,
            'window_start' => (string) $campaign->window_start,
            'window_end' => (string) $campaign->window_end,
            'starts_on' => $campaign->starts_on?->toDateString(),
            'ends_on' => $campaign->ends_on?->toDateString(),
        ];
    }
}
