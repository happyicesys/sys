<?php

namespace App\Console\Commands;

use App\Models\CustomerPeriodSummary;
use App\Models\CustomerSettlement;
use App\Services\Commission\RemovedSiteLocationFeeAccrual;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Posts the location fee of every removed site whose CURRENT month is already
 * locked but has no location fee row yet — the rows locked before locking did
 * it by itself (2026-11-16). Safe to re-run: RemovedSiteLocationFeeAccrual posts
 * once per customer + month. Dry run unless --apply.
 */
class AccrueRemovedLockedLocationFees extends Command
{
    protected $signature = 'settlement:accrue-removed-locked {--apply : Write the ledger rows.}';

    protected $description = 'Post the current-month location fee of removed sites whose month is already locked.';

    public function handle(RemovedSiteLocationFeeAccrual $accrual): int
    {
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $summaries = CustomerPeriodSummary::query()
            ->with('customer:id,code,name,removed_date')
            ->where('year_month', $monthStart->toDateString())
            ->whereNotNull('locked_at')
            ->whereHas('customer', fn ($q) => $q->whereBetween('removed_date', [$monthStart->toDateString(), $monthEnd->toDateString()]))
            ->get()
            ->unique('customer_id');

        $posted = 0;
        foreach ($summaries as $summary) {
            $c = $summary->customer;
            if (! $this->option('apply')) {
                $has = CustomerSettlement::query()
                    ->where('customer_id', $summary->customer_id)
                    ->where('entry_type', CustomerSettlement::TYPE_LOCATION_FEE)
                    ->where('year_month', $monthStart->toDateString())
                    ->exists();
                $this->line(sprintf('%s %s — %s', $c?->code, $c?->name, $has ? 'already posted' : 'would post if every row is locked and the fee is positive'));

                continue;
            }
            $row = $accrual->accrue($summary);
            if ($row) {
                $posted++;
                $this->line(sprintf('%s %s — posted %s $%s', $c?->code, $c?->name, $row->item, number_format($row->amount_cents / 100, 2)));
            }
        }

        $this->info(($this->option('apply') ? 'Posted ' : 'Dry run, ').$posted.' row(s); '.$summaries->count().' locked removed site(s) this month.');

        return self::SUCCESS;
    }
}
