<?php

namespace App\Console\Commands;

use App\Models\PaymentMethod;
use App\Models\VendTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Weekly, report-only: orphan sales (created from a NETS line, still no
 * TRADE) that look like the SAME sale as a card TRADE on the same machine for
 * the same cents that no line claims — the double-count a late TRADE leaves
 * when it could not be adopted (machine offline > 30 days, broken clock).
 * A human "Assign"s the line to the real sale on the report page; that
 * deletes the orphan (CardSettlementOrphanSales::release).
 *
 *   php artisan card-settlement:orphans-audit --days=35 --slack-days=3
 */
class AuditCardSettlementOrphans extends Command
{
    protected $signature = 'card-settlement:orphans-audit
        {--days=35 : how far back to look at orphan sales}
        {--slack-days=3 : how far apart the orphan and the unclaimed TRADE may be}';

    protected $description = 'List NETS-orphan sales that have an unclaimed card TRADE of the same amount on the same machine nearby (report only)';

    public function handle(): int
    {
        $since = Carbon::now()->subDays((int) $this->option('days'))->startOfDay();
        $slack = max(0, (int) $this->option('slack-days'));
        $cardMethodIds = PaymentMethod::query()->where('code', PaymentMethod::CODE_CARD_TERMINAL)->pluck('id')->all();

        $orphans = VendTransaction::query()
            ->withoutGlobalScopes()
            ->whereNotNull('card_settlement_row_id')
            ->where('is_found_in_transaction', false)
            ->where('transaction_datetime', '>=', $since)
            ->get(['id', 'order_id', 'vend_id', 'amount', 'transaction_datetime', 'card_settlement_row_id']);

        $adopted = VendTransaction::query()->withoutGlobalScopes()
            ->whereNotNull('card_settlement_row_id')->where('is_found_in_transaction', true)
            ->where('transaction_datetime', '>=', $since)->count();

        $this->info(sprintf('Orphans since %s: %d still without a TRADE, %d adopted by a late TRADE.', $since->toDateString(), $orphans->count(), $adopted));
        if ($orphans->isEmpty()) {
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($orphans->groupBy('vend_id') as $vendId => $group) {
            $lo = Carbon::parse($group->min('transaction_datetime'))->subDays($slack);
            $hi = Carbon::parse($group->max('transaction_datetime'))->addDays($slack);
            $unclaimed = VendTransaction::query()
                ->withoutGlobalScopes()
                ->where('vend_id', $vendId)
                ->whereBetween('transaction_datetime', [$lo, $hi])
                ->where('is_found_in_transaction', true)
                ->whereIn('payment_method_id', $cardMethodIds)
                ->whereIn('amount', $group->pluck('amount')->unique())
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('card_settlement_rows')
                    ->whereColumn('card_settlement_rows.matched_vend_transaction_id', 'vend_transactions.id'))
                ->get(['id', 'order_id', 'amount', 'transaction_datetime']);

            foreach ($group as $orphan) {
                foreach ($unclaimed->where('amount', $orphan->amount) as $sale) {
                    $rows[] = [$orphan->id, $orphan->card_settlement_row_id, $vendId, $orphan->amount, (string) $orphan->transaction_datetime, $sale->id, $sale->order_id, (string) $sale->transaction_datetime];
                }
            }
        }

        if (empty($rows)) {
            $this->info('No orphan has an unclaimed same-amount card TRADE nearby.');

            return self::SUCCESS;
        }
        $this->table(['Orphan txn', 'Report row', 'Vend', 'Cents', 'Orphan time', 'Unclaimed txn', 'Order ID', 'TRADE time'], $rows);
        $this->warn(sprintf('%d possible double count(s): Assign the report row to the unclaimed sale to merge.', count($rows)));

        return self::SUCCESS;
    }
}
