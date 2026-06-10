<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('send:channel-error-logs-email')->cron('30 16 * * *');
        $schedule->command('send:vend-transaction-no-entry-email')->cron('15 16 * * *');
        $schedule->command('delete:vend-data')->dailyAt('01:30');
        $schedule->command('delete:vend-temp')->dailyAt('01:30');
        $schedule->command('delete:vend-log')->dailyAt('01:35');
        $schedule->command('sync:vend-online-status')->everyMinute();
        // $schedule->command('scheduler:heartbeat')->everyFiveMinutes();
        $schedule->command('sync:totals-json')->dailyAt('00:10');
        $schedule->command('sync:product-unit-costs-timing')->dailyAt('00:05');
        $schedule->command('blind:recompute-costs')->dailyAt('00:20'); // after unit-cost timing settles
        $schedule->command('sync:product-vend-channels')->dailyAt('00:08');
        $schedule->command('export:vends-status')->monthly();
        $schedule->command('store:previous-day-vend-records')->daily();
        $schedule->command('grab:sync-all-menu')->daily();
        $schedule->command('sync:all-cms-vend-code-vend-prefix')->dailyAt('02:00');
        $schedule->command('copy:product-limit-from-yesterday')->at('23:57');
        $schedule->command('refund:payment-gateway-every-ten-minutes')->everyTenMinutes();
        $schedule->command('sync:voucher-status-daily')->daily();
        $schedule->command('telescope:prune --hours=48')->dailyAt('01:00');
        $schedule->command('save:today-stock-count')->dailyAt('23:59');
        $schedule->command('vend-temp:compute-metrics')->dailyAt('00:20');
        $schedule->command('gp:compute-metrics')->dailyAt('00:40');
        $schedule->command('customer-summary:compute')->dailyAt('01:00');
        $schedule->command('vend:retry-jobs')->everyMinute();
        $schedule->command('ops:freeze-stock-in')->everyMinute()->withoutOverlapping();
        $schedule->command('vend:cleanup-jobs')->dailyAt('02:00');
        // Keep vend_records & gp_metrics tallied to vend_transactions and auto-heal
        // any drifted day (late settlements, backdated uploads, cost backfills,
        // reassignments). Each run is a single grouped scan per table, so it's cheap.
        // Tiered to stay lean: a short trailing window nightly catches the common
        // recent drift, and a deep 45-day sweep weekly backstops older edits and
        // anything a missed nightly run skipped. Both run after the nightly rollups
        // settle; heals dispatch to the low queue so they don't compete with
        // realtime work.
        $schedule->command('reconcile:sales-rollups --days=14')->dailyAt('02:15')->withoutOverlapping();
        $schedule->command('reconcile:sales-rollups --days=45')->weeklyOn(0, '02:45')->withoutOverlapping();
        // Runs in the quiet window after the 02:00 cleanup, once yesterday's
        // gp_metrics have fully settled, so it competes with no heavy job.
        $schedule->command('ops:snapshot-daily')->dailyAt('03:00')->withoutOverlapping();

        // ── ONE-OFF historical backfill — fires once at 04:00 on 2026-06-06, then
        // never again (safe to delete this block after it has run). Rebuilds
        // gp_metrics back to 2024-12-03 so every trailing-30 window has data, then
        // seeds the per-machine L30d VendEarning snapshot from 2025-01-01. It runs
        // SEQUENTIALLY (the seed reads gp_metrics, so it must finish first — separate
        // queued jobs would race), kept low-impact with `nice -19` + a 1s/day throttle
        // and runInBackground, all in the 04:00 off-peak window. If
        // `SELECT MIN(txn_date) FROM gp_metrics` already shows <= 2024-12-03, you can
        // drop the gp:compute-metrics half to save time.
        $schedule->exec(
            'cd ' . base_path()
            . ' && nice -n 19 php artisan gp:compute-metrics --sync --sleep=1 --from=2024-12-03 --to=2026-06-05'
            . ' && nice -n 19 php artisan ops:snapshot-daily --from=2025-01-01 --to=2026-06-05 --force'
        )
            ->dailyAt('04:00')
            ->when(fn () => now()->toDateString() === '2026-06-06')
            ->runInBackground()
            ->withoutOverlapping();

        $schedule->command('remove:today-odd-transactions')->dailyAt('23:59');
        $schedule->job(new \App\Jobs\DetectTempTrends, 'low')->hourly();
        // $schedule->command('clean:ops-job-and-incoming-data')->daily();
        // $schedule->command('release:voucher-lock-every-2-mins')->everyMinute();
        $schedule->command('check:vend-fan-enabled')->everyTenMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
