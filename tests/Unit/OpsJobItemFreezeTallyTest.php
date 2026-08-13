<?php

namespace Tests\Unit;

use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use Tests\TestCase;

/**
 * Verifies computeTallyStatus() (the PHP port of opsJobItemChannelErrorCheck()
 * in Edit.vue) matches the original JS rules. No database required.
 *
 * Run: php artisan test --filter=OpsJobItemFreezeTallyTest
 */
class OpsJobItemFreezeTallyTest extends TestCase
{
    /**
     * @param  array<int, array{virtual_is_error:int, is_error_settle:int}>  $channels
     */
    private function item(int $status, array $channels): OpsJobItem
    {
        $item = new OpsJobItem;
        $item->status = $status;

        $models = array_map(function ($c) {
            $ch = new OpsJobItemChannel;
            // virtual_is_error is a generated column (not fillable) — set directly.
            $ch->virtual_is_error = $c['virtual_is_error'];
            $ch->is_error_settle = $c['is_error_settle'];

            return $ch;
        }, $channels);

        $item->setRelation('opsJobItemChannels', collect($models));

        return $item;
    }

    public function test_below_stock_in_is_always_all_tally(): void
    {
        // Even with an unsettled error, status < 3 means "All tally" (0).
        $item = $this->item(2, [['virtual_is_error' => 1, 'is_error_settle' => 0]]);
        $this->assertSame(0, $item->computeTallyStatus());
    }

    public function test_unsettled_error_is_status_2(): void
    {
        $item = $this->item(3, [
            ['virtual_is_error' => 0, 'is_error_settle' => 0],
            ['virtual_is_error' => 1, 'is_error_settle' => 0],
        ]);
        $this->assertSame(2, $item->computeTallyStatus());
    }

    public function test_settled_error_is_status_1(): void
    {
        $item = $this->item(3, [
            ['virtual_is_error' => 1, 'is_error_settle' => 1],
        ]);
        $this->assertSame(1, $item->computeTallyStatus());
    }

    public function test_no_errors_is_status_0(): void
    {
        $item = $this->item(3, [
            ['virtual_is_error' => 0, 'is_error_settle' => 0],
            ['virtual_is_error' => 0, 'is_error_settle' => 1],
        ]);
        $this->assertSame(0, $item->computeTallyStatus());
    }

    public function test_unsettled_outranks_settled_regardless_of_order(): void
    {
        $settledThenUnsettled = $this->item(3, [
            ['virtual_is_error' => 1, 'is_error_settle' => 1],
            ['virtual_is_error' => 1, 'is_error_settle' => 0],
        ]);
        $unsettledThenSettled = $this->item(3, [
            ['virtual_is_error' => 1, 'is_error_settle' => 0],
            ['virtual_is_error' => 1, 'is_error_settle' => 1],
        ]);

        $this->assertSame(2, $settledThenUnsettled->computeTallyStatus());
        $this->assertSame(2, $unsettledThenSettled->computeTallyStatus());
    }
}
