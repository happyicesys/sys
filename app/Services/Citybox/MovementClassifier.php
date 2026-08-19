<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\VisitWindowProvider;
use App\Enums\Citybox\MovementType;
use Carbon\CarbonInterface;

/**
 * Pure decision: given a signed delta and whether an ops visit overlapped the
 * window, name the movement (design §5b.1). No I/O beyond the injected
 * visit lookup, so it is trivially unit-testable.
 */
class MovementClassifier
{
    public function __construct(private VisitWindowProvider $visits) {}

    /** @return array{type: MovementType, ops_job_item_id: ?int} */
    public function classify(int $vendId, int $delta, CarbonInterface $windowStart, CarbonInterface $windowEnd): array
    {
        $visitItemId = $this->visits->visitOverlapping($vendId, $windowStart, $windowEnd);
        $inVisit = $visitItemId !== null;

        $type = match (true) {
            $delta < 0 && ! $inVisit => MovementType::Sale,
            $delta > 0 && $inVisit => MovementType::Restock,
            $delta < 0 && $inVisit => MovementType::Correction,
            default => MovementType::Unknown, // rise with no visit (their portal edit / AI recount)
        };

        return ['type' => $type, 'ops_job_item_id' => $inVisit ? $visitItemId : null];
    }
}
