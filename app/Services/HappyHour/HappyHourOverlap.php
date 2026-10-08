<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourCampaign;

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

        return HappyHourCampaign::query()->active()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->whereHas('vends', fn ($q) => $q->whereIn('vends.id', $vendIds))
            ->with(['vends' => fn ($q) => $q->whereIn('vends.id', $vendIds)])
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
