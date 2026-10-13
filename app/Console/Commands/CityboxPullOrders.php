<?php

namespace App\Console\Commands;

use App\Jobs\PullCityboxOrders;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Citybox\CityboxOrderSync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Pull CityBox orders (order_list, one call per ORDER day) into citybox_orders → vend_transactions
 * and the chiller stock ledger. Scheduled three ways (Kernel):
 *
 *   every minute     today (+ yesterday until 00:30)   sales and stock within a minute
 *   hourly           the last 3 days                   late pay-later capture, refund 3 → 4
 *   nightly 01:20    the last 31 days                  pay-later seen up to 14 days late
 *
 * A silent no-op unless the integration is enabled and a chiller is linked.
 * --from/--to (with --now) is the backfill: it runs here, in the foreground, and prints the totals.
 */
class CityboxPullOrders extends Command
{
    protected $signature = 'citybox:pull-orders
        {--days=1 : the last N order days, today included}
        {--from= : first order day (Y-m-d), backfill}
        {--to= : last order day (Y-m-d), default today}
        {--now : run in this process instead of queueing}';

    protected $description = 'Pull CityBox chiller orders into mark1 sales and the chiller stock ledger';

    public function handle(CityboxOrderSync $sync): int
    {
        if (! config('citybox.openapi.enabled') || ! CityboxOpenapiSync::hasLinkedVends()) {
            return self::SUCCESS;
        }

        $dates = $this->dates();
        if ($dates === []) {
            $this->error('No days to pull — check --from / --to.');

            return self::FAILURE;
        }

        if (! $this->option('now')) {
            PullCityboxOrders::dispatch($dates);

            return self::SUCCESS;
        }

        $stats = $sync->syncDays($dates);
        $this->info(sprintf('%d day(s), %d order(s) seen, %d changed, %d projected, %d moved stock, %d order error(s).',
            $stats['days'], $stats['seen'], $stats['changed'], $stats['projected'], $stats['stock_moved'], $stats['order_errors']));
        foreach ($stats['failed_days'] as $day => $error) {
            $this->warn("  {$day}: {$error}");
        }

        return $stats['failed_days'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    private function dates(): array
    {
        $today = Carbon::today();
        if ($from = $this->option('from')) {
            $start = Carbon::parse($from)->startOfDay();
            $end = $this->option('to') ? Carbon::parse($this->option('to'))->startOfDay() : $today;
        } else {
            $days = max(1, (int) $this->option('days'));
            $start = $today->copy()->subDays($days - 1);
            $end = $today;
            // Just after midnight an order from 23:59 may still be landing.
            if ($days === 1 && now()->lt($today->copy()->addMinutes(30))) {
                $start = $today->copy()->subDay();
            }
        }
        if ($end->gt($today)) {
            $end = $today;
        }

        $dates = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        return $dates;
    }
}
