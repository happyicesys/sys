<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\CityboxOrder;
use App\Models\CityboxOrderItem;
use App\Models\CityboxProduct;
use App\Models\Vend;
use App\Services\Citybox\DTO\ChillerOrder;
use App\Services\Citybox\DTO\ChillerOrderLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pull CityBox's orders, one ORDER day per call, into `citybox_orders` and hand each changed
 * order to the stock ledger and the sale projector (2026-10-13).
 *
 * Cheap by construction: one call per day (~50 ms), every order hashed, and an order whose hash
 * is unchanged and that has nothing left to settle costs one bulk `last_seen_at` update — so the
 * minute pull of today and the nightly 31-day sweep both touch only what moved. Every lookup is
 * batched per day (orders, vends, product links). A day is locked while it is processed, so the
 * minute, hourly and nightly runs never work the same day at once.
 */
class CityboxOrderSync
{
    public function __construct(
        private ChillerGateway $gateway,
        private CityboxOrderProjector $projector,
        private ChillerStockLedger $ledger,
    ) {}

    /**
     * @param  iterable<string>  $dates  Y-m-d order days
     * @return array{days:int, seen:int, changed:int, projected:int, stock_moved:int, failed_days:array<string,string>, order_errors:int}
     */
    public function syncDays(iterable $dates): array
    {
        $stats = ['days' => 0, 'seen' => 0, 'changed' => 0, 'projected' => 0, 'stock_moved' => 0, 'failed_days' => [], 'order_errors' => 0];
        $ledgerVends = [];
        $salesVends = [];

        foreach ($dates as $date) {
            $lock = Cache::lock('citybox:orders:'.$date, 300);
            if (! $lock->get()) {
                continue; // another run is on this day right now
            }
            try {
                $orders = $this->gateway->orders($date);
                $stats['days']++;
                $this->syncDay($orders, $stats, $ledgerVends, $salesVends);
            } catch (\Throwable $e) {
                $stats['failed_days'][$date] = $e->getMessage();
            } finally {
                $lock->release();
            }
        }

        foreach (array_keys($ledgerVends) as $vendId) {
            $this->ledger->refresh($vendId);
        }
        foreach ($salesVends as $vend) {
            SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');
        }

        return $stats;
    }

    /**
     * @param  Collection<int,ChillerOrder>  $orders
     * @param  array<int,true>  $ledgerVends
     * @param  array<int,Vend>  $salesVends
     */
    private function syncDay(Collection $orders, array &$stats, array &$ledgerVends, array &$salesVends): void
    {
        if ($orders->isEmpty()) {
            return;
        }
        $stats['seen'] += $orders->count();

        $existing = CityboxOrder::whereIn('citybox_order_id', $orders->map(fn (ChillerOrder $o) => $o->cityboxOrderId))
            ->with('items')->get()->keyBy('citybox_order_id');
        $vends = $this->vendsByEquipment($orders->map(fn (ChillerOrder $o) => $o->deviceId)->unique()->values());
        $productIds = CityboxProduct::whereIn('citybox_product_id', $orders->flatMap(fn (ChillerOrder $o) => array_map(fn (ChillerOrderLine $l) => $l->cityboxProductId, $o->lines))->unique()->values())
            ->whereNotNull('product_id')->pluck('product_id', 'citybox_product_id')->map(fn ($id) => (int) $id)->all();

        $untouched = [];
        foreach ($orders as $dto) {
            try {
                $hash = md5(json_encode($dto->rawPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $vend = $vends->get($dto->deviceId);
                /** @var CityboxOrder|null $row */
                $row = $existing->get($dto->cityboxOrderId);

                if ($row && $row->payload_hash === $hash && ! $this->hasWorkLeft($row, $vend)) {
                    $untouched[] = $row->id;

                    continue;
                }
                if (! $row || $row->payload_hash !== $hash) {
                    $row = $this->store($dto, $row, $hash, $vend, $productIds);
                    $stats['changed']++;
                }

                if ($this->ledger->apply($row, $vend, $productIds)) {
                    $stats['stock_moved']++;
                    $ledgerVends[$vend->id] = true;
                }
                if ($this->projector->project($row, $vend) !== null) {
                    $stats['projected']++;
                    $salesVends[$vend->id] = $vend;
                }
            } catch (\Throwable $e) {
                $stats['order_errors']++;
                Log::warning('Citybox order sync failed for one order', ['citybox_order_id' => $dto->cityboxOrderId, 'order_name' => $dto->orderName, 'error' => $e->getMessage()]);
            }
        }

        if ($untouched !== []) {
            CityboxOrder::whereIn('id', $untouched)->update(['last_seen_at' => now()]);
        }
    }

    /** An unchanged order still needs a pass while its sale or its stock is not settled. */
    private function hasWorkLeft(CityboxOrder $row, ?Vend $vend): bool
    {
        $saleOpen = $row->projection_error !== null && ! str_starts_with($row->projection_error, 'skip:')
            && ! str_starts_with($row->projection_error, 'status ');
        $stockOpen = $row->stock_deducted_at === null && ($row->stock_note === null || $row->stock_note === 'unknown_device');

        return $saleOpen || ($stockOpen && $vend !== null && ChillerStockLedger::enabled());
    }

    /** @param array<int,int> $productIds */
    private function store(ChillerOrder $dto, ?CityboxOrder $row, string $hash, ?Vend $vend, array $productIds): CityboxOrder
    {
        return DB::transaction(function () use ($dto, $row, $hash, $vend, $productIds) {
            $now = now();
            $attributes = [
                'order_name' => $dto->orderName,
                'citybox_equipment_id' => $dto->deviceId,
                'vend_id' => $vend?->id,
                'order_status' => $dto->status,
                'qty' => $dto->units(),
                'money_cents' => $dto->moneyCents,
                'good_money_cents' => $dto->goodMoneyCents,
                'refer' => $dto->refer,
                'refer_type' => $dto->referType,
                'pay_label' => $dto->payLabel,
                'ordered_at' => $dto->orderedAt,
                'paid_at' => $dto->paidAt,
                'payload_hash' => $hash,
                'raw_json' => $dto->rawPayload,
                'last_seen_at' => $now,
            ];
            if ($row) {
                if ((int) $row->order_status !== $dto->status) {
                    $attributes['status_changed_at'] = $now;
                    Log::info('Citybox order status changed', ['citybox_order_id' => $dto->cityboxOrderId, 'from' => $row->order_status, 'to' => $dto->status]);
                }
                $row->forceFill($attributes)->save();
                $row->items()->delete();
            } else {
                $row = CityboxOrder::create($attributes + ['citybox_order_id' => $dto->cityboxOrderId, 'first_seen_at' => $now]);
            }

            CityboxOrderItem::insert(array_map(fn (ChillerOrderLine $l) => [
                'order_id' => $row->id,
                'citybox_product_id' => $l->cityboxProductId,
                'product_id' => $productIds[$l->cityboxProductId] ?? null,
                'product_name' => mb_substr($l->name, 0, 255),
                'price_cents' => $l->priceCents,
                'qty' => $l->qty,
                'total_cents' => $l->totalCents,
                'created_at' => $now,
                'updated_at' => $now,
            ], $dto->lines));

            return $row->load('items');
        });
    }

    /**
     * The chillers these orders name, with everything the projector reads, in one query.
     *
     * @return Collection<string,Vend>
     */
    private function vendsByEquipment(Collection $equipmentIds): Collection
    {
        return Vend::withoutGlobalScopes()
            ->where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)
            ->whereIn('citybox_equipment_id', $equipmentIds)
            ->with([
                'operator', 'customer.locationType', 'customer.operator',
                'vendContract', 'vendModel', 'vendPrefix', 'productMapping.productMappingItems',
            ])
            ->get()
            ->keyBy('citybox_equipment_id');
    }
}
