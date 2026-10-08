<?php

namespace App\Console\Commands;

use App\Services\HappyHour\HappyHourPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Keeps every active Happy Hour campaign's freezer schedules current (HappyHourPlanner): closes
 * finished slots, ends sold-out ones, picks each day's lineup before its window, re-checks each slot
 * as it starts. Every minute.
 */
class HappyHourRunCommand extends Command
{
    protected $signature = 'happy-hour:run';

    protected $description = 'Plan, check and close Happy Hour slots for smart freezers';

    public function handle(HappyHourPlanner $planner): int
    {
        $stats = $planner->run(Carbon::now());
        if (array_sum($stats) > 0) {
            $this->line(collect($stats)->map(fn ($n, $k) => "{$k}={$n}")->implode(' '));
        }

        return self::SUCCESS;
    }
}
