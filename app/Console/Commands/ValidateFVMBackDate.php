<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Models\VendTransaction;
use App\Jobs\StoreVendsRecord;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Services\RunningNumberService;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;

class ValidateFVMBackDate extends Command
{
    /**
     * Validate that the monthly sales totals stored for the offline FVM machines
     * match the authoritative figures, and (with --apply) re-sync the months that
     * are out of sync by replacing that month's backfill transactions.
     *
     * These vends are 100% backfill-sourced (they cannot go online), so every
     * vend_transaction row for them is a backfill entry created by
     * CreateFVMBackDate. That makes "replace the whole month" the safe, exact way
     * to reconcile: delete the month's rows, recreate them evenly as cash so the
     * monthly total equals the authoritative figure.
     *
     * @var string
     */
    protected $signature = 'validate:fvm-back-date
        {--apply : Actually delete & recreate mismatched months. Without this flag the command only reports (dry-run).}
        {--vend= : Limit to a single vend code (e.g. 1809).}
        {--period= : Limit to a single period in YYMM form (e.g. 2604).}
        {--include-current : Also reconcile the current (incomplete) month. By default the current month is reported but never written.}
        {--tolerance=0 : Cents of allowed difference before a month is treated as a mismatch.}';

    protected $description = 'Validate & re-sync monthly sales totals for the offline FVM machines (1809, 1802, 1810).';

    protected $runningNumberService;

    /**
     * Authoritative monthly sales (in DOLLARS) per vend code, keyed by period (YYMM).
     * Source: latest sales screenshot provided by the operator. Update this table
     * whenever a newer authoritative figure is supplied.
     */
    const EXPECTED = [
        1809 => [
            2605 => 421,    2604 => 552,    2603 => 549,    2602 => 314,    2601 => 653,
            2512 => 499.5,  2511 => 616,    2510 => 428.5,  2509 => 672.5,  2508 => 570.5,
            2507 => 174.8,  2506 => 358.5,  2505 => 335.9,  2504 => 565,    2503 => 338.1,
            2502 => 565,    2501 => 390,    2412 => 582.5,  2411 => 541.5,  2410 => 730.5,
            2409 => 151.2,  2408 => 227.5,  2407 => 275.5,  2406 => 223,    2405 => 591,
            2404 => 562.4,  2403 => 542.2,  2402 => 367.5,  2401 => 586.8,  2312 => 448.2,
            2311 => 610.8,  2310 => 649,    2309 => 657.7,  2308 => 219.5,
        ],
        1802 => [
            2605 => 899,    2604 => 834,    2603 => 934,    2602 => 396,
        ],
        1810 => [
            2605 => 342,    2604 => 141,    2603 => 237,    2602 => 196,    2601 => 377,
            2512 => 403,    2511 => 328,    2510 => 433,    2509 => 290,    2508 => 369,
            2507 => 308,    2506 => 529,    2505 => 237,    2504 => 364,    2503 => 362,
            2502 => 471,    2501 => 527,    2412 => 540,    2411 => 345,
        ],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->runningNumberService = new RunningNumberService();
    }

    public function handle(): int
    {
        $apply          = (bool) $this->option('apply');
        $vendFilter     = $this->option('vend') !== null ? (int) $this->option('vend') : null;
        $periodFilter   = $this->option('period') !== null ? (int) $this->option('period') : null;
        $includeCurrent = (bool) $this->option('include-current');
        $tolerance      = (int) $this->option('tolerance'); // cents

        $currentPeriod = (int) Carbon::now()->format('ym'); // e.g. 2605

        $rows          = [];
        $monthsToApply = [];   // [vendCode => [period => expectedCents]]
        $affectedVends = [];   // vendCode => true

        foreach (self::EXPECTED as $vendCode => $months) {
            if ($vendFilter !== null && $vendCode !== $vendFilter) {
                continue;
            }

            $vend = Vend::where('code', $vendCode)->first();
            if (!$vend) {
                $this->error("Vend code {$vendCode} not found — skipping.");
                continue;
            }

            foreach ($months as $period => $expectedDollars) {
                if ($periodFilter !== null && (int) $period !== $periodFilter) {
                    continue;
                }

                [$start, $end] = $this->periodRange((int) $period);
                $expectedCents = (int) round($expectedDollars * 100);

                // Read the actual stored total straight from the table, bypassing
                // the model's operator/auth global scopes (no auth user in console).
                // Mirrors StoreVendsRecord's "Total Sales" rule: amount > 0, settled,
                // and non-error (error code null or in 0/6).
                $actualCents = (int) DB::table('vend_transactions as vt')
                    ->leftJoin('vend_channel_errors as vce', 'vt.vend_channel_error_id', '=', 'vce.id')
                    ->where('vt.vend_id', $vend->id)
                    ->whereBetween('vt.transaction_datetime', [$start, $end])
                    ->where('vt.amount', '>', 0)
                    ->where('vt.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
                    ->where(function ($q) {
                        $q->whereNull('vt.vend_channel_error_id')
                          ->orWhereIn('vce.code', [0, 6]);
                    })
                    ->sum('vt.amount');

                $diffCents  = $expectedCents - $actualCents;
                $isCurrent  = (int) $period === $currentPeriod;
                $isFuture   = (int) $period > $currentPeriod;
                $inSync     = abs($diffCents) <= $tolerance;

                if ($inSync) {
                    $status = 'OK';
                } elseif ($isFuture) {
                    $status = 'FUTURE (skip)';
                } elseif ($isCurrent && !$includeCurrent) {
                    $status = 'CURRENT (skip)';
                } else {
                    $status = 'MISMATCH';
                    $monthsToApply[$vendCode][(int) $period] = $expectedCents;
                    $affectedVends[$vendCode] = $vend;
                }

                $rows[] = [
                    $vendCode,
                    $period,
                    number_format($actualCents / 100, 2),
                    number_format($expectedCents / 100, 2),
                    ($diffCents >= 0 ? '+' : '') . number_format($diffCents / 100, 2),
                    $status,
                ];
            }
        }

        $this->table(
            ['Vend', 'Period', 'Actual ($)', 'Expected ($)', 'Diff ($)', 'Status'],
            $rows
        );

        $mismatchCount = collect($monthsToApply)->sum(fn ($m) => count($m));

        if ($mismatchCount === 0) {
            $this->info('All in-scope months are in sync. Nothing to do.');
            return self::SUCCESS;
        }

        if (!$apply) {
            $this->warn("{$mismatchCount} month(s) out of sync. Re-run with --apply to delete & recreate those months.");
            return self::SUCCESS;
        }

        // ---- APPLY: replace each mismatched month ----
        $this->newLine();
        $this->info('Applying backfill (replace-by-month, all cash)...');

        $touchedRanges = []; // [ [start,end], ... ] for vend_records re-roll

        foreach ($monthsToApply as $vendCode => $months) {
            $vend = $affectedVends[$vendCode];

            foreach ($months as $period => $expectedCents) {
                [$start, $end] = $this->periodRange((int) $period);

                DB::transaction(function () use ($vend, $period, $expectedCents, $start, $end) {
                    // 1) wipe the month's existing backfill rows for this vend
                    $deleted = DB::table('vend_transactions')
                        ->where('vend_id', $vend->id)
                        ->whereBetween('transaction_datetime', [$start, $end])
                        ->delete();

                    // 2) recreate evenly as cash so the monthly total == expected
                    $created = $this->recreateMonth($vend, (int) $period, $expectedCents);

                    $this->line(sprintf(
                        '  vend %d  %d : deleted %d, created %d  → $%s',
                        $vend->code,
                        $period,
                        $deleted,
                        $created,
                        number_format($expectedCents / 100, 2)
                    ));
                });

                $touchedRanges[] = [$start, $end];
            }
        }

        // 3) re-roll the daily vend_records for every touched month (synchronous so
        //    ordering is guaranteed), then refresh the cached totals JSON.
        foreach ($touchedRanges as [$start, $end]) {
            (new StoreVendsRecord($start->toDateString(), $end->toDateString(), true))->handle();
        }

        foreach ($affectedVends as $vend) {
            SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');
            if ($vend->customer) {
                SyncVendTransactionTotalsJson::dispatch($vend->customer)->onQueue('default');
            }
        }

        $this->newLine();
        $this->info("Done. Reconciled {$mismatchCount} month(s). vend_records re-rolled and totals JSON dispatched.");
        $this->warn('Note: this regenerates vend_records for the touched months (same effect as the monthly CreateFVMBackDate run). Period/customer summaries for unlocked months may be refreshed downstream.');

        return self::SUCCESS;
    }

    /**
     * Recreate one month of cash backfill transactions for a vend, distributing the
     * target total (in cents) evenly across the month's days with remainder pushed
     * to the earliest days. For the current month, distribution stops at "today" so
     * no future-dated rows are created. Returns the number of rows created.
     */
    protected function recreateMonth(Vend $vend, int $period, int $totalCents): int
    {
        $monthStart = $this->periodStart($period);
        $daysInMonth = $monthStart->daysInMonth;

        // Cap the current (incomplete) month at today so we never write future dates.
        $cap = $daysInMonth;
        if ($monthStart->isSameMonth(Carbon::now())) {
            $cap = min($daysInMonth, (int) Carbon::now()->day);
        }
        if ($cap < 1) {
            $cap = 1;
        }

        $daily     = intdiv($totalCents, $cap);
        $remainder = $totalCents % $cap;

        $created = 0;
        for ($i = 0; $i < $cap; $i++) {
            $amount = $daily + ($i < $remainder ? 1 : 0);
            if ($amount <= 0) {
                continue;
            }

            $date = $monthStart->copy()->addDays($i)->format('Y-m-d') . ' 00:00:00';

            VendTransaction::create([
                'transaction_datetime' => $date,
                'amount'               => $amount,            // cents, cash only
                'cashless_mfg'         => null,
                'order_id'             => $this->runningNumberService->getVendOrderIDBasedOnDate($vend, $date),
                'interface_type'       => null,
                'is_multiple'          => 0,
                'is_payment_received'  => 1,
                'items_json'           => [],
                'payment_method_id'    => 1,                  // 1 = cash
                'vend_id'              => $vend->id,
                'vend_channel_code'    => 0,
                'vend_channel_id'      => 0,
                'vend_channel_error_id' => null,
                'vend_transaction_json' => null,
                'payment_gateway_log_id' => null,
                'product_id'           => null,
                'customer_id'          => $vend->customer()->exists() ? $vend->customer->id : null,
                'location_type_id'     => $vend->customer()->exists() && $vend->customer->locationType()->exists() ? $vend->customer->locationType->id : null,
                'operator_id'          => $vend->customer()->exists() && $vend->customer->operator()->exists() ? $vend->customer->operator->id : 1,
                'unit_cost_id'         => null,
                'gst_vat_rate'         => $vend->customer()->exists() && $vend->customer->operator()->exists() ? $vend->customer->operator->gst_vat_rate : 0,
                'meta_json'            => [
                    'vend_code'       => $vend->code,
                    'customer_code'   => $vend->customer()->exists() ? $vend->customer->id + 20000 : null,
                    'customer_name'   => $vend->customer()->exists() ? $vend->customer->name : null,
                    'vend_prefix_id'  => $vend->vendPrefix()->exists() ? $vend->vendPrefix->id : null,
                    'vend_prefix_name' => $vend->vendPrefix()->exists() ? $vend->vendPrefix->name : null,
                    'backfill'        => 'validate:fvm-back-date',
                ],
                'is_zero_amount'       => false,
            ]);

            $created++;
        }

        return $created;
    }

    /** First moment of a YYMM period in the app timezone. */
    protected function periodStart(int $period): Carbon
    {
        $year  = 2000 + intdiv($period, 100);
        $month = $period % 100;
        return Carbon::create($year, $month, 1, 0, 0, 0, config('app.timezone'));
    }

    /** [start, end] datetime bounds covering a YYMM period. */
    protected function periodRange(int $period): array
    {
        $start = $this->periodStart($period);
        return [$start->copy()->startOfMonth(), $start->copy()->endOfMonth()];
    }
}
