<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\VisitWindowProvider;
use App\Models\CityboxDoorOpenLog;
use App\Models\OpsJobItem;
use Carbon\CarbonInterface;

/**
 * A "visit" = from a successful door-open on the vend until that item's
 * Stocked-In (completed_at) or, failing that, 2 h after the open (design
 * §5b.1). Any poll window overlapping it is inside the visit.
 */
class DoorOpenLogVisitWindowProvider implements VisitWindowProvider
{
    public function visitOverlapping(int $vendId, CarbonInterface $start, CarbonInterface $end): ?int
    {
        $opens = CityboxDoorOpenLog::opened()->where('vend_id', $vendId)
            ->whereBetween('requested_at', [$end->copy()->subHours(2), $end])
            ->orderByDesc('requested_at')->get(['ops_job_item_id', 'requested_at']);

        foreach ($opens as $open) {
            $visitEnd = $open->requested_at->copy()->addHours(2);
            if ($open->ops_job_item_id) {
                $completed = OpsJobItem::withoutGlobalScopes()->where('id', $open->ops_job_item_id)->value('completed_at');
                if ($completed) {
                    $visitEnd = min($visitEnd, \Carbon\Carbon::parse($completed)->addMinutes(10)); // + a beat for the A frame
                }
            }
            if ($open->requested_at <= $end && $visitEnd >= $start) {
                return $open->ops_job_item_id ?: -1; // -1 = a Settings-page open with no item, still a visit
            }
        }

        return null;
    }
}
