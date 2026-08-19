<?php

namespace App\Contracts\Citybox;

use Carbon\CarbonInterface;

/**
 * Answers "was an ops visit in progress on this vend during [start, end]?"
 * Step 6 implements it from citybox_door_open_logs (first door-open → A-frame
 * or 2 h cut-off). Until then the null implementation says "no", so every
 * fall is a sale and every rise is unknown — honest, never a false restock.
 */
interface VisitWindowProvider
{
    /** @return int|null the ops_job_item_id whose visit overlaps the window, or null */
    public function visitOverlapping(int $vendId, CarbonInterface $start, CarbonInterface $end): ?int;
}
