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
        $schedule->command('sync:vend-online-status')->everyMinute();
        $schedule->command('sync:totals-json')->dailyAt('00:10');
        $schedule->command('sync:product-unit-costs-timing')->dailyAt('00:05');
        $schedule->command('export:vends-status')->monthly();
        $schedule->command('store:previous-day-vend-records')->daily();
        $schedule->command('grab:sync-all-menu')->daily();
        $schedule->command('sync:all-cms-vend-code-vend-prefix')->dailyAt('02:00');
        $schedule->command('copy:product-limit-from-yesterday')->daily();
        $schedule->command('refund:payment-gateway-every-ten-minutes')->everyTenMinutes();
        $schedule->command('sync:voucher-status-daily')->daily();
        $schedule->command('telescope:prune --hours=48')->dailyAt('01:00');
        $schedule->command('save:today-stock-count')->dailyAt('23:59');
        $schedule->command('vend-temp:compute-metrics')->dailyAt('00:20');
        $schedule->command('gp:compute-metrics')->dailyAt('00:40');
        $schedule->command('vend:retry-jobs')->everyMinute();
        $schedule->command('vend:cleanup-jobs')->dailyAt('02:00');
        // $schedule->command('release:voucher-lock-every-2-mins')->everyMinute();
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
