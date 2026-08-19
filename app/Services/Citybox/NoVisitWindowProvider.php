<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\VisitWindowProvider;
use Carbon\CarbonInterface;

/** Default until step 6: no visit information exists yet. */
class NoVisitWindowProvider implements VisitWindowProvider
{
    public function visitOverlapping(int $vendId, CarbonInterface $start, CarbonInterface $end): ?int
    {
        return null;
    }
}
