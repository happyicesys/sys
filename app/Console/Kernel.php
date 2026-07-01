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
        $schedule->command('delete:vend-coin-float-log')->dailyAt('01:40');
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
        // Apply any future-dated placement-contract changes whose effective date
        // has arrived BEFORE the summary recompute, so the new contract version
        // is in customer_contract_logs when customer-summary:compute runs.
        $schedule->command('contract:apply-scheduled')->dailyAt('00:50')->withoutOverlapping();
        $schedule->command('customer-summary:compute')->dailyAt('01:00');
        $schedule->command('vend:retry-jobs')->everyMinute();
        $schedule->command('ops:freeze-stock-in')->everyMinute()->withoutOverlapping();
        // Safety net: re-enqueue any freeze-eligible items the observer missed
        // (e.g. bulk/raw status updates that bypass model events).
        $schedule->command('ops:freeze-queue-reconcile')->hourly()->withoutOverlapping();
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
        // Monthly deep backstop — a long window catches transactions that
        // settle / refund / get reassigned MORE than 45 days after their sale
        // date, which the nightly (14d) and weekly (45d) sweeps never revisit.
        // One grouped scan per table keeps even this wide window cheap. Off-peak
        // on the 1st, after the 03:00 ops snapshot; heals go to the low queue.
        $schedule->command('reconcile:sales-rollups --days=400')->monthlyOn(1, '04:30')->withoutOverlapping();

        // Transactions-index headline daily rollup (per-operator-per-day totals).
        // Same moving-window rationale as the sales rollups above: nightly 14-day
        // + weekly 45-day catch late settlements. Excludes today (still mutating).
        // Populates vend_transaction_daily_summaries; the read path stays on the
        // live query until transactions:rollup-verify shows an empty diff and the
        // ENABLE_TRANSACTIONS_ROLLUP flag is turned on. Spaced away from 02:15 so
        // the two vend_transactions scans don't contend.
        // Gated by the same kill switch as the read path: with the flag OFF
        // (default) NOTHING here auto-runs, so the feature is fully dormant until
        // you opt in. Manual `php artisan transactions:rollup-daily` still works
        // for backfill/verification regardless of the flag.
        $schedule->command('transactions:rollup-daily --days=14')->dailyAt('03:20')->withoutOverlapping()
            ->when(fn() => (bool) config('reporting.transactions_rollup_enabled'));
        $schedule->command('transactions:rollup-daily --days=45')->weeklyOn(0, '03:40')->withoutOverlapping()
            ->when(fn() => (bool) config('reporting.transactions_rollup_enabled'));
        // Locked Site Summaries are deliberately NOT re-healed by reconcile, so a
        // late settlement on an already-locked month would silently stay stale.
        // Audit last completed month's locked rows against live vend_transactions
        // and log any drift for manual review. Read-only — never writes.
        $schedule->command('customer-summary:validate-sales --all --locked-only')
            ->monthlyOn(1, '05:00')
            ->appendOutputTo(storage_path('logs/locked-summary-audit.log'));
        // Runs in the quiet window after the 02:00 cleanup, once yesterday's
        // gp_metrics have fully settled, so it competes with no heavy job.
        $schedule->command('ops:snapshot-daily')->dailyAt('03:00')->withoutOverlapping();

        // Accrue the previous month's Net Loc Fee into each owing site's
        // settlement ledger (Site Summary ▸ Payment History) as a charge.
        // On the 1st, AFTER the 01:00 customer-summary:compute has finalised
        // last month's figures. Idempotent — safe to re-run. Replaces the old
        // CMS-pulled "owe" remark as the source of truth for outstanding.
        $schedule->command('settlement:generate-monthly')->monthlyOn(1, '02:30')->withoutOverlapping();

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
        // is_fan_enabled is now latched at ingest in SyncVendParameter (real-time).
        // This fleet scan (json_extract of parameter_json per row, ~4.3s) is kept
        // only as a daily safety net to backfill any vend the ingest path missed
        // (e.g. rows whose fan>1000 predates the latch), instead of running 144x/day.
        $schedule->command('check:vend-fan-enabled')->dailyAt('04:10');
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
