<?php

namespace Tests\Unit;

use App\Contracts\Citybox\VisitWindowProvider;
use App\Enums\Citybox\MovementType;
use App\Services\Citybox\MovementClassifier;
use App\Services\Citybox\NoVisitWindowProvider;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use PHPUnit\Framework\TestCase;

class MovementClassifierTest extends TestCase
{
    private function visitAt(?int $itemId): VisitWindowProvider
    {
        return new class($itemId) implements VisitWindowProvider
        {
            public function __construct(private ?int $id) {}

            public function visitOverlapping(int $vendId, CarbonInterface $s, CarbonInterface $e): ?int
            {
                return $this->id;
            }
        };
    }

    public function test_fall_outside_visit_is_sale(): void
    {
        $c = new MovementClassifier(new NoVisitWindowProvider);
        $r = $c->classify(1, -1, CarbonImmutable::now()->subMinutes(3), CarbonImmutable::now());
        $this->assertSame(MovementType::Sale, $r['type']);
        $this->assertNull($r['ops_job_item_id']);
    }

    public function test_rise_outside_visit_is_unknown_never_restock(): void
    {
        $c = new MovementClassifier(new NoVisitWindowProvider);
        $this->assertSame(MovementType::Unknown, $c->classify(1, +5, CarbonImmutable::now()->subMinutes(3), CarbonImmutable::now())['type']);
    }

    public function test_rise_inside_visit_is_restock_and_carries_item(): void
    {
        $c = new MovementClassifier($this->visitAt(4242));
        $r = $c->classify(1, +5, CarbonImmutable::now()->subMinutes(3), CarbonImmutable::now());
        $this->assertSame(MovementType::Restock, $r['type']);
        $this->assertSame(4242, $r['ops_job_item_id']);
    }

    public function test_fall_inside_visit_is_correction(): void
    {
        $c = new MovementClassifier($this->visitAt(4242));
        $this->assertSame(MovementType::Correction, $c->classify(1, -2, CarbonImmutable::now()->subMinutes(3), CarbonImmutable::now())['type']);
    }
}
