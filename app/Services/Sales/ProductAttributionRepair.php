<?php

namespace App\Services\Sales;

use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Models\VendTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Re-attribute settled sales that were booked against the WRONG planogram.
 *
 * Origin (2026-09-23): machine 2487 was bound to UEE-UEI_2609 — a mapping for a
 * different machine family — from 09-17 19:27 to 09-23 10:08. The board kept
 * selling the 2608a planogram it physically held, so revenue was collected
 * correctly, but {@see \App\Services\VendTransactionService::processMapping}
 * resolves the product by looking the sale's channel code up in the BOUND
 * mapping. 2609 carries nothing on channels 11-19, so 118 of 135 sales were
 * written with product_id = NULL, unit_cost = 0 and — because gstVatRate is only
 * read off the resolved product's operator — gst_vat_rate = 0 as well. The
 * consequences, all financial:
 *
 *   revenue      = amount / (1 + 0/100)  = amount   (GST never extracted)
 *   gross_profit = revenue - 0           = revenue  (booked at 100% margin)
 *
 * The other 17 resolved against 2609's own items, so they carry a real but WRONG
 * product and therefore the wrong COGS.
 *
 * The repair re-runs exactly the ingest arithmetic with the planogram the
 * machine really held, which the caller names explicitly — it is never guessed.
 * Only the money/attribution columns move; the sale, its amount, its channel and
 * its dispense verdict are untouched.
 *
 * SCOPE GUARD — this repairs only the "wrong mapping was bound" shape. A sale
 * whose vend_channels row has since been DELETED (the dangling-FK cause, which
 * is ~99% of unattributed sales fleet-wide, see UNATTRIBUTED_SALES_AUDIT_2026-09-23.md)
 * is deliberately NOT touched: without the channel row there is no channel code
 * to look up, so the product would have to be inferred rather than resolved.
 * Multi-item parents are skipped too — their items carry their own attribution
 * in vend_transaction_items and that is a separate recipe.
 */
class ProductAttributionRepair
{
    /**
     * Rows in range whose attribution does not match $mapping, with the values
     * the ingest path would have written. Read-only; safe for a dry run.
     *
     * @return Collection<int, array{txn: VendTransaction, channel_code: string, product_id: int, unit_cost: int, unit_cost_id: ?int, gst_vat_rate: float, revenue: int, gross_profit: int, gross_profit_margin: float}>
     */
    public function plan(Vend $vend, ProductMapping $mapping, Carbon $from, Carbon $to): Collection
    {
        $items = ProductMappingItem::where('product_mapping_id', $mapping->id)
            ->with(['product.unitCosts', 'product.operator'])
            ->get()
            ->keyBy(fn ($item) => (string) $item->channel_code);

        return VendTransaction::withoutGlobalScopes()
            ->with('vendChannel')
            ->where('vend_id', $vend->id)
            ->whereBetween('transaction_datetime', [$from, $to])
            ->where('is_refunded', false)
            ->where('is_zero_amount', false)
            // The settlement gate, via the shared scope — never the magic 2.
            ->countsAsSale()
            // A multiple purchase's parent row carries no single product — its
            // children do. Leave the whole shape alone.
            ->where('is_multiple', false)
            ->get()
            ->map(function (VendTransaction $txn) use ($items) {
                // The channel row is the only place the sale's channel CODE
                // survives; a deleted row is out of scope (see class docblock).
                $code = $txn->vendChannel?->code;
                if ($code === null) {
                    return null;
                }

                $item = $items->get((string) $code);
                if (! $item || ! $item->product) {
                    return null;
                }

                $target = $this->resolve($txn, $item);

                // Already correct — nothing to write.
                if ($txn->product_id === $target['product_id']
                    && (int) $txn->unit_cost === $target['unit_cost']
                    && (int) $txn->revenue === $target['revenue']) {
                    return null;
                }

                return array_merge($target, ['txn' => $txn, 'channel_code' => (string) $code]);
            })
            ->filter()
            ->values();
    }

    /**
     * The ingest arithmetic, reproduced. Mirrors processMapping() + create():
     * the cost is the product's CURRENT intrinsic unit cost (the same row ingest
     * would have read — deliberately not the date-effective one, so a repaired
     * row is identical to one booked correctly at the time), the GST rate comes
     * off the product's operator, and revenue is the GST-exclusive amount.
     *
     * unit_costs.cost is stored in cents; UnitCost's accessor divides by 100 and
     * processMapping multiplies it straight back, so vend_transactions.unit_cost
     * equals the raw column. Read it raw here rather than round-tripping.
     */
    private function resolve(VendTransaction $txn, ProductMappingItem $item): array
    {
        $product = $item->product;

        $unitCost = $product->unitCosts->firstWhere('is_current', true);
        $unitCostValue = $unitCost ? (int) round($unitCost->cost * 100) : 0;

        $gstVatRate = (float) ($product->operator->gst_vat_rate ?? 0);
        $revenue = (int) round($txn->amount / (1.00 + ($gstVatRate / 100)));
        $grossProfit = $revenue - $unitCostValue;

        return [
            'product_id' => $product->id,
            'product_mapping_id' => $item->product_mapping_id,
            'product_mapping_item_id' => $item->id,
            'unit_cost' => $unitCostValue,
            'unit_cost_id' => $unitCost?->id,
            'gst_vat_rate' => $gstVatRate,
            'revenue' => $revenue,
            'gross_profit' => $grossProfit,
            'gross_profit_margin' => $revenue ? (($grossProfit * 100) / $revenue) : 0,
        ];
    }

    /**
     * Write the plan. Every row keeps what it held in meta_json.attribution_repair
     * so the change is reversible and auditable, and each touched calendar day is
     * registered dirty so the nightly rollup rebuild picks up the moved GP —
     * gp_metrics and the daily facts are pre-aggregated and do NOT follow a
     * straight column update.
     *
     * @return array{repaired: int, days: array<int, string>}
     */
    public function apply(Collection $plan): array
    {
        $days = [];

        DB::transaction(function () use ($plan, &$days) {
            foreach ($plan as $entry) {
                /** @var VendTransaction $txn */
                $txn = $entry['txn'];

                $meta = $txn->meta_json ?: [];
                $meta['attribution_repair'] = [
                    'at' => now()->toDateTimeString(),
                    'was_product_id' => $txn->product_id,
                    'was_product_mapping_id' => $txn->product_mapping_id,
                    'was_product_mapping_item_id' => $txn->product_mapping_item_id,
                    'was_unit_cost' => (int) $txn->unit_cost,
                    'was_unit_cost_id' => $txn->unit_cost_id,
                    'was_gst_vat_rate' => (float) $txn->gst_vat_rate,
                    'was_revenue' => (int) $txn->revenue,
                    'was_gross_profit' => (int) $txn->gross_profit,
                    'channel_code' => $entry['channel_code'],
                ];

                $txn->forceFill([
                    'product_id' => $entry['product_id'],
                    'product_mapping_id' => $entry['product_mapping_id'],
                    'product_mapping_item_id' => $entry['product_mapping_item_id'],
                    'unit_cost' => $entry['unit_cost'],
                    'unit_cost_id' => $entry['unit_cost_id'],
                    'gst_vat_rate' => $entry['gst_vat_rate'],
                    'revenue' => $entry['revenue'],
                    'gross_profit' => $entry['gross_profit'],
                    'gross_profit_margin' => $entry['gross_profit_margin'],
                    'meta_json' => $meta,
                ])->save();

                $days[Carbon::parse($txn->transaction_datetime)->toDateString()] = true;
            }
        });

        $days = array_keys($days);
        sort($days);

        // AFTER the commit — the queue is redis and a worker can start rebuilding
        // from an open transaction otherwise (same rule as the TRADE ingest).
        $registry = app(DirtyDayRegistry::class);
        foreach ($days as $day) {
            $registry->mark($day);
        }

        return ['repaired' => $plan->count(), 'days' => $days];
    }
}
