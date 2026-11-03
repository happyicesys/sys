<?php

namespace App\Console\Commands;

use App\Models\CardPaymentIntent;
use App\Models\Vend;
use App\Models\VendChannelQtyAdjustment;
use App\Models\VendTransaction;
use App\Services\Freezer\FreezerSaleAudit;
use App\Services\Freezer\FreezerStockLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves a Smart Freezer's stock for T05 sales the AI audited before stock followed the charge
 * (2026-11-03). Each sale is stamped `audit.stock_moved`, so a second run moves nothing. A hand
 * count made after a sale already includes what really left, so it is listed for a person to judge.
 * Dry run unless --apply.
 */
class FreezerApplyAiStock extends Command
{
    protected $signature = 'freezer:apply-ai-stock {vend : machine code} {--since= : first sale to look at, Y-m-d H:i:s} {--apply : write the result}';

    protected $description = 'Make a Smart Freezer\'s stock follow the AI on T05 sales audited before stock did';

    public function handle(FreezerSaleAudit $audit, FreezerStockLedger $ledger): int
    {
        $vend = Vend::withoutGlobalScopes()->where('code', $this->argument('vend'))
            ->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)->first();
        if (! $vend) {
            $this->error('No Smart Freezer with that code.');

            return self::FAILURE;
        }
        if (! $this->option('since')) {
            $this->error('Give --since: stock counted by hand after a sale must not be moved for it.');

            return self::FAILURE;
        }

        $sales = VendTransaction::withoutGlobalScopes()
            ->where('vend_id', $vend->id)
            ->where('transaction_datetime', '>=', $this->option('since'))
            ->where('meta_json->audit->source', 'smart_freezer_ai')
            ->orderBy('id')
            ->get();

        $rows = [];
        $total = [];
        foreach ($sales as $sale) {
            $saleAudit = (array) ((array) $sale->meta_json)['audit'];
            $intent = isset($saleAudit['intent_id']) ? CardPaymentIntent::find($saleAudit['intent_id']) : null;
            if (array_key_exists('stock_moved', $saleAudit) || ! $intent) {
                continue;
            }
            $charged = isset($saleAudit['basket_charged'])
                ? collect($saleAudit['basket_charged'])->mapWithKeys(fn ($units, $id) => [(int) $id => (int) $units])->all()
                : null;
            $taken = $audit->stockBasket($intent, (int) ($saleAudit['charged_cents'] ?? 0), $charged);
            if ($taken === null) {
                continue;
            }

            $moved = $this->option('apply')
                ? $this->apply($vend, $sale, $ledger, $taken)
                : $ledger->chargeDifference($sale, $taken);
            foreach ($moved as $productId => $back) {
                $total[$productId] = ($total[$productId] ?? 0) + $back;
            }
            $rows[] = [$sale->order_id, $sale->transaction_datetime, json_encode($moved)];
        }

        $this->table(['sale', 'at', 'product => back(+) / off(-)'], $rows);
        $this->table(['product', 'net'], collect($total)->map(fn ($net, $id) => [$id, $net])->values()->all());

        $counts = VendChannelQtyAdjustment::query()->where('vend_id', $vend->id)
            ->where('created_at', '>=', $this->option('since'))->count();
        if ($counts > 0) {
            $this->warn("{$counts} hand count(s) on this machine since then: check them before trusting the result.");
        }
        $this->info($this->option('apply') ? 'Applied.' : 'Dry run, re-run with --apply.');

        return self::SUCCESS;
    }

    /** @return array<int, int> */
    private function apply(Vend $vend, VendTransaction $sale, FreezerStockLedger $ledger, array $taken): array
    {
        return DB::transaction(function () use ($vend, $sale, $ledger, $taken) {
            $row = VendTransaction::withoutGlobalScopes()->whereKey($sale->id)->lockForUpdate()->first();
            $meta = (array) $row->meta_json;
            if (array_key_exists('stock_moved', (array) ($meta['audit'] ?? []))) {
                return [];
            }
            $moved = $ledger->followCharge($vend, $row, $taken);
            $meta['audit']['stock_moved'] = $moved;
            $row->forceFill(['meta_json' => $meta])->saveQuietly();

            return $moved;
        });
    }
}
