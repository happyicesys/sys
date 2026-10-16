<?php

namespace App\Console\Commands;

use App\Models\HappyHourSlot;
use App\Models\Vend;
use App\Services\HappyHour\HappyHourSales;
use App\Services\Sales\DirtyDayRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair (2026-10-16): vending baskets tagged with Happy Hour before
 * HappyHourSales::repriceVendingBasket existed carry each promo item at its board price. This runs
 * the same rewrite over them, re-opens their slots' totals (the minute run recounts) and dirties
 * their days for the 02:00 rollup rebuild (product revenue / GP read item prices).
 */
class HappyHourRepriceVendingBasketsCommand extends Command
{
    protected $signature = 'happy-hour:reprice-vending-baskets {--since=2026-10-12 : first sale date to look at} {--apply : write; without it, only list}';

    protected $description = 'Rewrite Happy Hour vending basket items to the promo price the screen charged';

    public function handle(HappyHourSales $sales, DirtyDayRegistry $dirty): int
    {
        $apply = (bool) $this->option('apply');
        $baskets = DB::table('vend_transactions as t')
            ->join('vends as v', 'v.id', '=', 't.vend_id')
            ->where('v.machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE)
            ->where('t.transaction_datetime', '>=', Carbon::parse((string) $this->option('since'))->startOfDay())
            ->where('t.is_multiple', 1)
            ->whereNotNull('t.happy_hour_slot_id')
            ->orderBy('t.id')
            ->get(['t.id', 't.vend_id', 't.amount', 't.transaction_datetime']);

        $repaired = 0;
        $skipped = 0;
        foreach ($baskets as $b) {
            $plan = $sales->planVendingBasketReprice((int) $b->id);
            if ($plan['state'] === 'unprovable') {
                $skipped++;
                $this->warn("#{$b->id}: items do not add up to the sale after repricing ({$plan['total']} vs {$plan['amount']}) — left as is");

                continue;
            }
            if ($plan['state'] !== 'reprice') {
                continue; // already adds up, or nothing tagged to reprice
            }
            if (! $apply) {
                $this->line("#{$b->id} vend {$b->vend_id} {$b->transaction_datetime}: sale {$b->amount}, items → ".implode(', ', $plan['prices']));
                $repaired++;

                continue;
            }
            if ($sales->repriceVendingBasket((int) $b->id)) {
                $repaired++;
                $slotIds = DB::table('vend_transaction_items')->where('vend_transaction_id', $b->id)->pluck('happy_hour_slot_id')->filter()->unique();
                HappyHourSlot::whereIn('id', $slotIds)->whereNotNull('units_sold')->update(['units_sold' => null]);
                $dirty->mark(Carbon::parse($b->transaction_datetime));
            }
        }

        $this->info(($apply ? 'Repriced' : 'Would reprice')." {$repaired} basket(s)".($skipped ? ", {$skipped} left as is" : '').'.');

        return self::SUCCESS;
    }
}
