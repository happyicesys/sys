<?php

namespace App\Console\Commands\CardTerminal;

use App\Models\CardPaymentEvent;
use Illuminate\Console\Command;

/**
 * card_payment_events grew to ~263k rows in five weeks, 98% of them `provider.http` lines of the
 * T05 readiness poll (`terminal/status`, no order). Those are routine; every row tied to a payment
 * (custom_order_id set) and every other event is kept for good (audit SF2-02, 2026-11-07).
 */
class PruneCardPaymentEvents extends Command
{
    public const KEEP_DAYS = 30;

    protected $signature = 'card-payments:prune-events {--days='.self::KEEP_DAYS.' : keep this many days of status polls}';

    protected $description = 'Delete T05 status-poll HTTP lines (no order) older than N days';

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));
        // The first id inside the window, found along the primary key: the deletes below then range
        // on the key instead of scanning created_at, which has no index of its own.
        $boundary = CardPaymentEvent::query()->where('created_at', '>=', now()->subDays($days))->orderBy('id')->value('id')
            ?? ((int) CardPaymentEvent::query()->max('id') + 1);

        $deleted = 0;
        do {
            $batch = CardPaymentEvent::query()
                ->where('id', '<', $boundary)
                ->where('event', 'provider.http')
                ->whereNull('custom_order_id')
                ->limit(5000)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} status-poll line(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
