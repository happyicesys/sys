<?php

namespace App\Services\Citybox;

use App\Models\CityboxOrder;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\Citybox\DTO\ChillerOrder;
use App\Services\Refund\RefundTicketService;
use App\Services\Sales\DirtyDayRegistry;
use App\Services\Sales\LateTradeTracker;
use App\Support\AutoRefundSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A CityBox order → one vend_transactions row (Brian, 2026-10-13: their transactions are the
 * source of truth), so chiller sales sit in every sales, GP and product report beside the
 * vending fleet with no report change.
 *
 * The row follows the house rules of VendTransactionService::createVendTransaction:
 *  - `order_id` = their order number; `(order_id, vend_id)` is unique, so a re-run never doubles.
 *  - `transaction_datetime` = when the money came in (pay-later can be days after the goods left).
 *  - One unit = `is_multiple` 0, product on the row. More units = `is_multiple` 1 plus ONE
 *    vend_transaction_items row per unit (the table has no qty; GpMetricsAggregator counts rows).
 *  - Unit cost = the product's current cost, GST = the product operator's rate, revenue / GP as
 *    the ingest computes them; channel = the chiller's row for that SKU, else 0 (an off-planogram
 *    SKU still sells, and every report resolves the product from the row itself).
 *  - Paid through CityBox (CityboxPayMethod), never a rail mark1 reconciles.
 *
 * Statuses: 1 paid and 3 refund applied are sales; 4 refunded is a sale whose money went back —
 * `is_refunded` + REFUNDED + source `citybox_refunded`, through the refund-ticket writer like every
 * other refund. 0 unpaid and 2 paying are goods taken with no money (Brian, 2026-10-21: show
 * them, marked unpaid): settlement UNPAID, Payment "Unpaid", never a sale, and the same row turns
 * SETTLED in place if CityBox later reports it paid. 5 rejected and −1 cancelled are not projected.
 * A test-rig amount (VendTransaction::
 * ODD_TRANSACTION_AMOUNTS) is never projected, or the 23:59 sweep and the next pull would fight
 * over it; its goods still leave the ledger.
 *
 * A changed order rewrites its row and items in place; a day it leaves or lands on is dirtied for
 * the 02:00 rollup rebuild.
 */
class CityboxOrderProjector
{
    public const SKIP_TEST_AMOUNT = 'skip:test_amount';

    public const SKIP_NOT_A_SALE = 'skip:status';

    /**
     * Goods taken, no money collected: listed as an Unpaid row, never a sale. The same statuses
     * ChillerStockLedger counts as goods taken; 5 rejected and −1 cancelled move no stock and owe
     * nothing, so they stay unlisted.
     */
    public const UNPAID = [ChillerOrder::STATUS_UNPAID, ChillerOrder::STATUS_PAYING];

    /** Every status that gets a vend_transactions row; anything else is skipped. */
    public const PROJECTED = [ChillerOrder::STATUS_PAID, ChillerOrder::STATUS_REFUND_APPLIED, ChillerOrder::STATUS_REFUNDED, ...self::UNPAID];

    /** @var array<int,int> payment_methods.code → id */
    private array $methodIds;

    private ?int $successErrorId;

    /** @var array<int,array<int,VendChannel>> vend id → product id → channel */
    private array $channels = [];

    /** @var array<int,Product> */
    private array $products = [];

    public function __construct(
        private LateTradeTracker $lateTrades,
        private RefundTicketService $tickets,
    ) {
        $this->methodIds = PaymentMethod::whereIn('code', CityboxPayMethod::ALL)->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
        $this->successErrorId = VendChannelError::where('code', 0)->value('id');
    }

    /** Returns the vend_transactions id, or null when the order is not (yet) a sale. */
    public function project(CityboxOrder $order, ?Vend $vend): ?int
    {
        if (! $vend) {
            return $this->fail($order, 'unknown_device');
        }

        $status = (int) $order->order_status;
        $projected = in_array($status, self::PROJECTED, true);
        $existing = $order->vend_transaction_id
            ? VendTransaction::withoutGlobalScopes()->find($order->vend_transaction_id)
            : VendTransaction::withoutGlobalScopes()->where('vend_id', $vend->id)->where('order_id', $order->order_name)->first();

        if (! $projected || in_array($order->money_cents, VendTransaction::ODD_TRANSACTION_AMOUNTS, true)) {
            if ($existing) {
                // Was projected, is not now (an unknown status, or a test amount). Never seen; keep
                // the row and say so rather than deleting money from the reports silently.
                Log::warning('Citybox order stopped being a sale after it was projected', ['citybox_order_id' => $order->citybox_order_id, 'status' => $status, 'vend_transaction_id' => $existing->id]);

                return $this->mark($order, $existing->id, 'status '.$status.' after projection');
            }

            return $this->mark($order, null, $projected ? self::SKIP_TEST_AMOUNT : self::SKIP_NOT_A_SALE);
        }

        $lines = $this->unitLines($order, $vend);
        if (isset($lines['error'])) {
            return $this->fail($order, $lines['error']);
        }

        $txnId = DB::transaction(function () use ($order, $vend, $existing, $lines, $status) {
            $header = $this->header($order, $vend, $lines, $status, $existing);
            if ($existing) {
                $before = $existing->transaction_datetime ? $existing->transaction_datetime->copy() : null;
                $existing->forceFill($header)->save();
                $existing->vendTransactionItems()->delete();
                $txn = $existing;
                if ($before && $before->toDateString() !== $txn->transaction_datetime->toDateString()) {
                    $this->dirty($before);
                }
            } else {
                $txn = VendTransaction::create($header);
            }
            if ($txn->is_multiple) {
                foreach ($lines as $unit) {
                    VendTransactionItem::create([
                        'vend_transaction_id' => $txn->id,
                        'product_id' => $unit['product_id'],
                        'product_mapping_item_id' => $unit['product_mapping_item_id'],
                        'unit_cost_id' => $unit['unit_cost_id'],
                        'unit_cost' => $unit['unit_cost'],
                        'unit_price_amount' => $unit['price'],
                        'vend_channel_id' => $unit['channel_id'],
                        'vend_channel_code' => $unit['channel_code'],
                        'vend_channel_error_code' => 0,
                        'vend_channel_error_id' => $this->successErrorId,
                        'is_refunded' => $status === ChillerOrder::STATUS_REFUNDED,
                    ]);
                }
            }
            $this->lateTrades->noteLanded($txn);
            if (! in_array($status, self::UNPAID, true)) {
                $this->bumpLastSale($vend, $txn->transaction_datetime);
            }

            return $txn->id;
        });

        if ($status === ChillerOrder::STATUS_REFUNDED && ! ($existing?->is_refunded)) {
            try {
                $this->tickets->markAutoRefundedByCharge($order->order_name, null, $txnId);
            } catch (\Throwable $e) {
                Log::error('Refund ticket auto-resolve after CityBox refund failed', ['order_name' => $order->order_name, 'error' => $e->getMessage()]);
            }
        }

        return $this->mark($order, $txnId, null);
    }

    /**
     * One entry per unit, resolved to our product, channel, mapping item and cost.
     *
     * @return list<array<string,mixed>>|array{error:string}
     */
    private function unitLines(CityboxOrder $order, Vend $vend): array
    {
        $channels = $this->channelsFor($vend);
        $mappingItems = $vend->productMapping?->productMappingItems?->whereNotNull('product_id')->keyBy('product_id') ?? collect();
        $units = [];
        foreach ($order->items as $item) {
            if (! $item->product_id) {
                return ['error' => 'unmapped_product:'.$item->citybox_product_id];
            }
            $product = $this->product((int) $item->product_id);
            if (! $product) {
                return ['error' => 'missing_product:'.$item->product_id];
            }
            $cost = $product->unitCosts->firstWhere('is_current', true);
            $channel = $channels[(int) $item->product_id] ?? null;
            for ($i = 0; $i < (int) $item->qty; $i++) {
                $units[] = [
                    'product_id' => $product->id,
                    'product' => $product,
                    'price' => (int) $item->price_cents,
                    'unit_cost_id' => $cost?->id,
                    'unit_cost' => $cost ? $cost->cost * 100 : 0,
                    'channel_id' => $channel?->id ?? 0,
                    'channel_code' => $channel?->code ?? 0,
                    'product_mapping_item_id' => $mappingItems->get($product->id)?->id,
                ];
            }
        }
        if ($units === []) {
            return ['error' => 'no_lines'];
        }

        return $units;
    }

    /** @param list<array<string,mixed>> $units */
    private function header(CityboxOrder $order, Vend $vend, array $units, int $status, ?VendTransaction $existing): array
    {
        // The Site is frozen at the first projection, like every sale row: a later rebind of the
        // chiller must not move an old order to the new Site.
        $customer = $existing
            ? ($existing->customer_id ? Customer::withoutGlobalScopes()->with(['locationType', 'operator'])->find($existing->customer_id) : null)
            : $vend->customer;
        $single = count($units) === 1 ? $units[0] : null;
        $gst = (float) ($units[0]['product']->operator?->gst_vat_rate ?? $vend->operator?->gst_vat_rate ?? 0);
        $amount = (int) $order->money_cents;
        $unitCost = array_sum(array_column($units, 'unit_cost'));
        $revenue = $amount / (1.00 + $gst / 100);
        $refunded = $status === ChillerOrder::STATUS_REFUNDED;
        $unpaid = in_array($status, self::UNPAID, true);
        // A sale is booked when the money came in; an unpaid one when the goods left.
        $at = ($unpaid ? null : $order->paid_at) ?? $order->ordered_at ?? now();

        return [
            'transaction_datetime' => $at,
            'amount' => $amount,
            'is_zero_amount' => $amount === 0,
            'order_id' => $order->order_name,
            'is_multiple' => $single === null,
            'is_payment_received' => true,
            'items_json' => [],
            'payment_method_id' => $this->methodIds[CityboxPayMethod::codeFor($order->pay_label)] ?? $this->methodIds[CityboxPayMethod::OTHER] ?? null,
            'qty' => count($units),
            'success_qty' => count($units),
            'dispensed_qty' => count($units),
            'vend_id' => $vend->id,
            'vend_channel_code' => $single['channel_code'] ?? 0,
            'vend_channel_id' => $single['channel_id'] ?? 0,
            'vend_channel_error_id' => $this->successErrorId,
            'vend_contract_id' => $vend->vendContract?->id,
            'vend_model_id' => $vend->vendModel?->id,
            'vend_prefix_id' => $vend->vendPrefix?->id,
            'vend_transaction_json' => ['src' => 'citybox', 'citybox_order_id' => $order->citybox_order_id, 'order' => $order->raw_json],
            'product_id' => $single['product_id'] ?? null,
            'product_mapping_id' => $existing?->product_mapping_id ?? ($vend->product_mapping_id ?: null),
            'product_mapping_item_id' => $single['product_mapping_item_id'] ?? null,
            'customer_id' => $customer?->id,
            'location_type_id' => $customer?->locationType?->id,
            'operator_id' => $customer?->operator?->id ?? $vend->operator_id ?? 1,
            'unit_cost_id' => $single['unit_cost_id'] ?? null,
            'gst_vat_rate' => $gst,
            'revenue' => $revenue,
            'unit_cost' => $unitCost,
            'gross_profit' => $grossProfit = $revenue - $unitCost,
            'gross_profit_margin' => $revenue ? ($grossProfit * 100) / $revenue : 0,
            'settlement_status' => match (true) {
                $refunded => VendTransaction::SETTLEMENT_REFUNDED,
                $unpaid => VendTransaction::SETTLEMENT_UNPAID,
                default => VendTransaction::SETTLEMENT_SETTLED,
            },
            'is_refunded' => $refunded,
            'auto_refund_source' => $refunded ? AutoRefundSource::CITYBOX_REFUNDED : null,
            'meta_json' => [
                'vend_code' => $vend->code,
                'customer_code' => $customer ? ($customer->id + 20000) : null,
                'customer_name' => $customer?->name,
                'citybox' => array_filter([
                    'order_status' => $status,
                    'refund' => $status === ChillerOrder::STATUS_REFUND_APPLIED ? 'applied' : ($refunded ? 'refunded' : null),
                    'pay' => $order->pay_label,
                    'refer' => $order->refer,
                    'refer_type' => $order->refer_type,
                    'ordered_at' => $order->ordered_at?->toDateTimeString(),
                    'paid_at' => $order->paid_at?->toDateTimeString(),
                ], fn ($v) => $v !== null),
            ],
        ];
    }

    /** @return array<int,VendChannel> product id → the vend's row for it, active first */
    private function channelsFor(Vend $vend): array
    {
        if (! isset($this->channels[$vend->id])) {
            $this->channels[$vend->id] = VendChannel::where('vend_id', $vend->id)->whereNotNull('product_id')
                ->orderByDesc('is_active')->orderBy('id')->get(['id', 'vend_id', 'product_id', 'code', 'is_active'])
                ->unique('product_id')->keyBy('product_id')->all();
        }

        return $this->channels[$vend->id];
    }

    private function product(int $id): ?Product
    {
        if (! array_key_exists($id, $this->products)) {
            $this->products[$id] = Product::withoutGlobalScopes()->with(['unitCosts', 'operator'])->find($id);
        }

        return $this->products[$id];
    }

    private function bumpLastSale(Vend $vend, CarbonInterface $at): void
    {
        if ($vend->last_vend_transaction_at === null || $at->greaterThan($vend->last_vend_transaction_at)) {
            $vend->forceFill(['last_vend_transaction_at' => $at])->saveQuietly();
        }
    }

    /** A day the sale LEFT (its pay time moved) is rebuilt too; the registry ignores today. */
    private function dirty(CarbonInterface $day): void
    {
        DB::afterCommit(fn () => app(DirtyDayRegistry::class)->mark($day));
    }

    private function fail(CityboxOrder $order, string $reason): ?int
    {
        return $this->mark($order, $order->vend_transaction_id, $reason);
    }

    private function mark(CityboxOrder $order, ?int $txnId, ?string $reason): ?int
    {
        $order->forceFill([
            'vend_transaction_id' => $txnId,
            'projected_at' => $txnId ? now() : $order->projected_at,
            'projection_error' => $reason,
        ])->save();

        return $reason === null ? $txnId : null;
    }
}
