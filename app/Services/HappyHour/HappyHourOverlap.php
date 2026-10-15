<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourCampaign;
use App\Models\Scopes\OperatorHappyHourCampaignScope;

/**
 * One machine, one Happy Hour at a time: two active campaigns may share a machine only if their
 * dates, days or hours never meet. Checked when a campaign is saved; the slot table's unique
 * (vend_id, starts_at) index is the backstop.
 */
final class HappyHourOverlap
{
    /**
     * @param  array<string, mixed>  $data  the validated campaign fields
     * @param  list<int>  $vendIds
     * @return list<string> one message per clashing campaign
     */
    public static function conflicts(array $data, array $vendIds, ?int $ignoreId): array
    {
        if ($vendIds === []) {
            return [];
        }

        // Every operator's campaigns: a clash with one the viewer cannot see is still a clash. Only a
        // machine the other campaign ALSO picked by name clashes: one it holds through a group gives
        // way to a pick by name (HappyHourMachineGroups).
        return HappyHourCampaign::query()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->active()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->whereHas('vends', fn ($q) => $q->whereIn('vends.id', $vendIds)->whereNull('happy_hour_campaign_vend.via_group'))
            ->with(['vends' => fn ($q) => $q->whereIn('vends.id', $vendIds)->wherePivotNull('via_group')])
            ->get()
            ->filter(fn (HappyHourCampaign $other) => self::meets($data, $other))
            ->map(fn (HappyHourCampaign $other) => sprintf(
                'Machine %s is already in "%s" at overlapping times.',
                $other->vends->map(fn ($v) => $v->codeLabel())->implode(', '),
                $other->name,
            ))
            ->values()
            ->all();
    }

    /**
     * The machines among $vendIds that another active campaign already holds THROUGH A GROUP at
     * overlapping times — the ones a live group must not take (first come keeps it).
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $vendIds
     * @return list<int>
     */
    public static function takenVendIds(array $data, array $vendIds, ?int $ignoreId): array
    {
        if ($vendIds === []) {
            return [];
        }

        // Group-held rows only: a machine another campaign picked by name is HappyHourMachineGroups'
        // in-force rule (it leaves the group only once that campaign is in force).
        return HappyHourCampaign::query()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->active()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->whereHas('vends', fn ($q) => $q->whereIn('vends.id', $vendIds)->whereNotNull('happy_hour_campaign_vend.via_group'))
            ->with(['vends' => fn ($q) => $q->whereIn('vends.id', $vendIds)->wherePivotNotNull('via_group')->select('vends.id')])
            ->get()
            ->filter(fn (HappyHourCampaign $other) => self::meets($data, $other))
            ->flatMap(fn (HappyHourCampaign $other) => $other->vends->pluck('id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Another active campaign whose owner's operator group shares an operator with this one's, with the
     * same live group at overlapping times — refused even before the group has a member (both would
     * claim every machine that joins later). Since 2026-10-15 an HIPL campaign's groups cover its
     * siblings, so an HIMD campaign with "All VM - Big Board" clashes with an HIPL one.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $groups
     * @return list<string>
     */
    public static function groupConflicts(array $data, array $groups, int $operatorId, ?int $ignoreId): array
    {
        if ($groups === []) {
            return [];
        }

        return HappyHourCampaign::query()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->active()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->whereNotNull('machine_groups')
            ->get()
            ->filter(fn (HappyHourCampaign $other) => array_intersect(HappyHourMachineGroups::operatorGroup($operatorId), HappyHourMachineGroups::operatorGroup((int) $other->operator_id)) !== []
                && array_intersect($groups, (array) $other->machine_groups) !== []
                && self::meets($data, $other))
            ->map(fn (HappyHourCampaign $other) => sprintf(
                '%s is already in "%s" at overlapping times.',
                implode(' / ', array_map(fn ($g) => HappyHourMachineGroups::label($g), array_values(array_intersect($groups, (array) $other->machine_groups)))),
                $other->name,
            ))
            ->values()->all();
    }

    /** @param  array<string, mixed>  $data */
    private static function meets(array $data, HappyHourCampaign $other): bool
    {
        if (((int) $data['days_mask'] & $other->days_mask) === 0) {
            return false;
        }
        $start = substr((string) $data['window_start'], 0, 5);
        $end = substr((string) $data['window_end'], 0, 5);
        if ($start >= substr((string) $other->window_end, 0, 5) || substr((string) $other->window_start, 0, 5) >= $end) {
            return false;
        }
        $from = $data['starts_on'] ?? null;
        $to = $data['ends_on'] ?? null;
        $otherFrom = $other->starts_on?->toDateString();
        $otherTo = $other->ends_on?->toDateString();

        return ! (($to !== null && $otherFrom !== null && $to < $otherFrom)
            || ($otherTo !== null && $from !== null && $otherTo < $from));
    }
}
