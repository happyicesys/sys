<?php

namespace App\Console\Commands;

use App\Services\Sales\MissingTradeMarker;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Stamp channel error 99 ("Machine transaction not found (NA)") on gateway
 * sales whose day is over and whose TRADE never arrived. See
 * App\Services\Sales\MissingTradeMarker and NA_ERROR_CODE_PLAN_2026-09-08.md.
 *
 *   php artisan sales:mark-missing-trade                     # report the nightly window
 *   php artisan sales:mark-missing-trade --apply             # nightly (00:01, scheduler)
 *   php artisan sales:mark-missing-trade --from=2026-08-01 --to=2026-09-08          # seed report
 *   php artisan sales:mark-missing-trade --from=2026-08-01 --to=2026-09-08 --apply  # seed
 *
 * --to is exclusive at 00:00 of that day (a day is only marked once it is over).
 * Marking moves no revenue, GP or qty figure (99 is a sale code), so no rollup
 * rebuild is queued.
 */
class MarkMissingTrade extends Command
{
    protected $signature = 'sales:mark-missing-trade
        {--from= : Window start (Y-m-d). Default: settings.missing_trade_marked_until, else config sales.missing_trade_floor}
        {--to= : Window end, exclusive, at 00:00 (Y-m-d). Default: today}
        {--apply : Write the marks. Without it the command only reports}
        {--chunk= : Header rows per chunk (default config sales.missing_trade_chunk)}';

    protected $description = 'Mark gateway sales with no TRADE after their day is over as channel error 99 (NA).';

    public function handle(MissingTradeMarker $marker): int
    {
        [$from, $until] = $marker->nightlyWindow();
        if ($this->option('from')) {
            $from = Carbon::parse($this->option('from'))->startOfDay();
        }
        if ($this->option('to')) {
            $until = Carbon::parse($this->option('to'))->startOfDay();
        }
        $apply = (bool) $this->option('apply');
        $chunk = $this->option('chunk') ? max(1, (int) $this->option('chunk')) : null;

        $this->info(sprintf('Window %s → %s (exclusive), mode=%s', $from->toDateTimeString(), $until->toDateTimeString(), $apply ? 'APPLY' : 'report'));

        if ($until->lte($from)) {
            $this->line('Nothing to do: window is empty (watermark already at or past --to).');

            return self::SUCCESS;
        }

        $result = $marker->mark($from, $until, $apply, $chunk);

        if ($result->headers === 0) {
            $this->info('No gateway sale without a TRADE in this window.');
        } else {
            $this->table(
                ['Day', 'Headers'],
                collect($result->perDay)->sortKeys()->map(fn ($n, $d) => [$d, $n])->values()->all()
            );
            $this->info(sprintf(
                '%s %d header row(s) and %d item row(s) across %d day(s).',
                $apply ? 'Marked' : 'Would mark',
                $result->headers,
                $result->items,
                count($result->perDay)
            ));
        }

        if ($apply) {
            $this->line('Watermark advanced to '.$until->toDateTimeString().'.');
        }

        return self::SUCCESS;
    }
}
