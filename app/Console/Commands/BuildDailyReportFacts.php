<?php

namespace App\Console\Commands;

use App\Services\Reporting\DailyFactsBuilder;
use App\Services\Reporting\DimensionRebuilder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Populate the pre-aggregated daily sales-analysis report facts, so the 8:30am
 * report becomes a handful of fast SELECTs instead of scraping + re-deriving.
 *
 * Runs AFTER gp_metrics (00:40) + reconcile:sales-rollups (02:15) + ops
 * snapshot (03:00), so fact_site_daily reads a fully-healed gp_metrics.
 *
 *   php artisan report:build-daily-facts                 # dims + last 3 closed days + records
 *   php artisan report:build-daily-facts --days=14       # wider self-heal window
 *   php artisan report:build-daily-facts --date=2026-07-19
 *   php artisan report:build-daily-facts --from=2026-04-01 --to=2026-07-19   # backfill
 *   php artisan report:build-daily-facts --skip-dims     # facts only (weekly pass)
 *   php artisan report:build-daily-facts --only=hourly   # one fact table
 *
 * Idempotent + backfillable: every fact is rebuilt per-day (delete-by-date then
 * insert); dimensions are rebuilt wholesale. Never processes today (still
 * mutating) unless an explicit --date/--to says so.
 *
 * The nightly SCHEDULE is gated by config('reporting.daily_facts_enabled')
 * (default false) — this command always runs when invoked manually, for
 * backfill + verification, regardless of the flag.
 */
class BuildDailyReportFacts extends Command
{
    protected $signature = 'report:build-daily-facts
        {--date= : Process a single YYYY-MM-DD}
        {--from= : Range start (YYYY-MM-DD)}
        {--to= : Range end (YYYY-MM-DD)}
        {--days=3 : Default self-heal window of completed days (excludes today)}
        {--skip-dims : Do not rebuild dim_calendar / dim_site_cohort}
        {--only= : Limit facts to one of: hourly|site|rainfall (records still refresh)}
        {--sleep=0 : Seconds to pause between days (gentle backfills)}';

    protected $description = 'Build pre-aggregated daily report facts (hourly sales, site-daily, hourly rainfall, day-type records) + cohort/calendar dimensions.';

    public function handle(DimensionRebuilder $dims, DailyFactsBuilder $facts): int
    {
        $only = $this->option('only');
        if ($only && ! in_array($only, ['hourly', 'site', 'rainfall'], true)) {
            $this->error('--only must be one of: hourly, site, rainfall');
            return self::FAILURE;
        }

        [$start, $end] = $this->determineRange();
        if ($start->gt($end)) {
            $this->error('Invalid date range (from > to).');
            return self::FAILURE;
        }

        // 1. Dimensions first — the facts denormalize cohort / day_type_bucket
        //    from them, so they must be current before any fact is built.
        if (! $this->option('skip-dims')) {
            $this->info('Rebuilding dimensions ...');
            $calN = $dims->rebuildCalendar();
            $cohortN = $dims->rebuildSiteCohorts();
            $this->info(" - dim_calendar: {$calN} rows,  dim_site_cohort: {$cohortN} sites");
        }

        // 2. Per-day facts.
        $this->info(sprintf('Building facts %s .. %s (only=%s) ...',
            $start->toDateString(), $end->toDateString(), $only ?: 'all'));

        $days = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $this->buildDay($facts, $cursor->copy(), $only);
            $days++;

            $sleep = (int) $this->option('sleep');
            if ($sleep > 0) {
                sleep($sleep);
            }
            $cursor->addDay();
        }

        // 3. Day-type records are ALL-TIME since the floor, so always recompute
        //    up to yesterday — independent of the processed range. Capping this
        //    to the range end would let a targeted/backfill run of an older day
        //    shrink an existing record below its true all-time value.
        $recN = $facts->refreshDayTypeRecords();
        $this->info("Refreshed {$recN} day-type record row(s).");

        $this->info("Done. {$days} day(s) built.");
        return self::SUCCESS;
    }

    private function buildDay(DailyFactsBuilder $facts, Carbon $day, ?string $only): void
    {
        $label = $day->toDateString();
        try {
            if (! $only || $only === 'hourly') {
                $facts->buildSalesHourly($day);
            }
            if (! $only || $only === 'site') {
                $facts->buildSiteDaily($day);
            }
            if (! $only || $only === 'rainfall') {
                $facts->buildRainfallHourly($day);
            }
            $this->line("   - {$label} ok");
        } catch (\Throwable $e) {
            // Log + skip the day (matching ProcessGpMetricsRange): one bad date
            // must not abort a backfill or the nightly self-heal.
            Log::warning('report:build-daily-facts day failed', [
                'date'    => $label,
                'message' => $e->getMessage(),
            ]);
            $this->warn("   - {$label} SKIPPED: {$e->getMessage()}");
        }
    }

    /**
     * Resolve [start, end]. Priority: --date, then --from/--to, else the last
     * --days completed days (never today).
     *
     * @return array{0:Carbon,1:Carbon}
     */
    private function determineRange(): array
    {
        if ($d = $this->option('date')) {
            $day = Carbon::parse($d)->startOfDay();
            return [$day, $day->copy()];
        }

        if ($this->option('from') || $this->option('to')) {
            $yesterday = Carbon::today()->subDay();
            $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : $yesterday->copy();
            $to = $this->option('to') ? Carbon::parse($this->option('to'))->startOfDay() : $yesterday->copy();
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            return [$from, $to];
        }

        // Default self-heal: yesterday back through --days completed days.
        $days = max(1, (int) $this->option('days'));
        $to = Carbon::today()->subDay();
        $from = $to->copy()->subDays($days - 1);
        return [$from, $to];
    }
}
