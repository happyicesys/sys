<?php

namespace Tests\Support\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Enums\Citybox\DeviceState;
use App\Exceptions\CityboxApiException;
use App\Services\Citybox\DTO\ChillerCatalogItem;
use App\Services\Citybox\DTO\ChillerDevice;
use App\Services\Citybox\DTO\ChillerOrder;
use App\Services\Citybox\DTO\ChillerPriceGroupRow;
use App\Services\Citybox\DTO\ChillerStockLine;
use App\Services\Citybox\DTO\RestockSession;
use App\Services\Citybox\DTO\StockCount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * In-memory ChillerGateway for tests. Seed it with the LIVE fixture shapes
 * (captured 2026-08-17/19) and assert on what services do — no HTTP, no URL
 * matching, no token dance. Records every write so tests can assert them.
 */
class FakeChillerGateway implements ChillerGateway
{
    /** @var array<string,array> equipment_id => box_list row */
    public array $devices = [];

    /** @var array<string,array[]> equipment_id => device_product goods rows */
    public array $stock = [];

    /** @var array<string,array[]> equipment_id => shipping_product goods rows */
    public array $par = [];

    /** @var array[] product_list rows */
    public array $catalogRows = [];

    /** @var array<string,string> equipment_id => state code */
    public array $states = [];

    /** @var array<string,string> equipment_id => error message to throw on deviceStock */
    public array $stockErrors = [];

    /** @var array<string,string> equipment_id => refusal message for openForRestock */
    public array $openRefusals = [];

    /** @var array<int,array{device:string,operatorRef:string,msgId:string}> */
    public array $opens = [];

    /** @var array<int,array{device:string,msgId:string,rows:array}> */
    public array $submits = [];

    /** @var array<string,array[]> order day (Y-m-d) => order_list rows (their raw shape) */
    public array $orderRows = [];

    /** @var array<int,string> days whose order_list call fails */
    public array $orderErrors = [];

    /** @var array[] price_group_list rows */
    public array $priceGroupRows = [];

    /** @var array<string,int|string> equipment_id => their raw signalStrength; absent = their 设备不存在 */
    public array $signals = [];

    /** @var array<int,array{device:string,msgId:string,before:string,after:string}> */
    public array $photoSubmits = [];

    /** Set to make deliver_pic_submit refuse, as CityBox does for an unknown msg_id. */
    public ?string $failPhotoSubmit = null;

    private int $openSeq = 0;

    // ── seeding helpers (real shapes) ──────────────────────────────────────

    public function seedDevice(string $eq, string $name = '#1', string $type = 'visual-2', int $online = 1, int|string $status = 1): self
    {
        $this->devices[$eq] = [
            'equipment_id' => $eq, 'name' => $name, 'status' => $status, 'type' => $type,
            'heartbeat_last_recovery' => '2026-08-19 01:14:36', 'heartbeat_last_offline' => '2026-08-12 19:12:37',
            'equipment_online_status' => $online, 'equipment_online_status_str' => $online === 1 ? '在线' : '离线',
            'equipment_status_str' => '启运',
        ];
        $this->states[$eq] = $online === 1 ? 'FREE' : 'NOT_FOUND';

        return $this;
    }

    public function seedStock(string $eq, array $lines): self
    {
        $this->stock[$eq] = array_map(fn ($l) => self::goodsRow($l), $lines);

        return $this;
    }

    public function seedPar(string $eq, array $lines): self
    {
        $this->par[$eq] = array_map(fn ($l) => self::goodsRow($l, par: true), $lines);

        return $this;
    }

    /** @param array{id:int,name:string,qty?:int,layer?:int,price?:string,active?:string} $l */
    private static function goodsRow(array $l, bool $par = false): array
    {
        $row = [
            'thumbnailPic' => 'https://cdn/'.$l['id'].'.png',
            'quantity' => (string) ($l['qty'] ?? 0),
            'name' => $l['name'],
            'product_id' => (string) $l['id'],
            'price' => $l['price'] ?? '0.10',
            'volume' => '500ml', 'unit' => 'Bottle', 'class_id' => '8', 'class_name' => '饮料',
            'layer' => (string) ($l['layer'] ?? 1),
        ];
        if (! $par) {
            $row['active_price'] = $l['active'] ?? $row['price'];
        }

        return $row;
    }

    // ── ChillerGateway ─────────────────────────────────────────────────────

    public function listDevices(array $filters = []): Collection
    {
        $rows = collect($this->devices);
        if (isset($filters['equipment_id'])) {
            $rows = $rows->only([$filters['equipment_id']]);
        }

        return $rows->values()->map(fn ($r) => ChillerDevice::fromApi($r));
    }

    public function deviceStock(string $deviceId): Collection
    {
        if (isset($this->stockErrors[$deviceId])) {
            throw new CityboxApiException($this->stockErrors[$deviceId], 400);
        }
        if (! isset($this->devices[$deviceId])) {
            throw new CityboxApiException('此设备没有权限', 400);
        }

        return collect($this->stock[$deviceId] ?? [])->map(fn ($g) => ChillerStockLine::fromApi($g));
    }

    /** Simulate an API blip on shipping_product. */
    public bool $failRestockConfig = false;

    /** Set to a message to make submitCount throw, as CityBox does on a stale session. */
    public ?string $failSubmit = null;

    /** Runs once the submit was accepted: a test's stand-in for what happens meanwhile on our side. */
    public ?\Closure $afterSubmit = null;

    public function restockConfig(string $deviceId): Collection
    {
        if ($this->failRestockConfig) {
            throw new \App\Exceptions\CityboxApiException('Citybox openapi api/Openapi/shipping_product unreachable');
        }

        return collect($this->par[$deviceId] ?? [])->map(fn ($g) => ChillerStockLine::fromApi($g));
    }

    public function catalog(array $filters = []): Collection
    {
        return collect($this->catalogRows)->map(fn ($r) => ChillerCatalogItem::fromApi($r));
    }

    public function deviceState(string $deviceId): DeviceState
    {
        return DeviceState::fromApi($this->states[$deviceId] ?? 'NOT_FOUND');
    }

    public function openForRestock(string $deviceId, string $operatorRef): RestockSession
    {
        if (isset($this->openRefusals[$deviceId])) {
            throw new CityboxApiException($this->openRefusals[$deviceId], 400);
        }
        $msgId = 'sg-fake-'.(++$this->openSeq);
        $this->opens[] = ['device' => $deviceId, 'operatorRef' => $operatorRef, 'msgId' => $msgId];
        $this->states[$deviceId] = 'OPENING';

        return new RestockSession($deviceId, $msgId, (string) (70 + $this->openSeq), CarbonImmutable::now());
    }

    public function orders(string $date): Collection
    {
        if (in_array($date, $this->orderErrors, true)) {
            throw new CityboxApiException('Citybox openapi api/Openapi/order_list unreachable after retries');
        }

        return collect($this->orderRows[$date] ?? [])->map(fn ($r) => ChillerOrder::fromApi($r));
    }

    /**
     * A real order_list row. $lines: [[citybox product id, qty, price '1.70'], …].
     *
     * @param  array<int,array{0:int,1:int,2:string}>  $lines
     */
    public function seedOrder(string $date, int $id, string $eq, array $lines, int $status = 1, string $time = '10:00:00', ?string $paidAt = null, string $pay = 'CARD'): self
    {
        $products = [];
        $total = 0;
        foreach ($lines as [$pid, $qty, $price]) {
            $cents = \App\Services\Citybox\CityboxMoney::toCents($price) * $qty;
            $total += $cents;
            $products[] = ['product_id' => (string) $pid, 'product_name' => 'P'.$pid, 'price' => $price, 'qty' => (string) $qty, 'total_money' => number_format($cents / 100, 2, '.', '')];
        }
        $money = number_format($total / 100, 2, '.', '');
        $this->orderRows[$date] = array_values(array_filter($this->orderRows[$date] ?? [], fn ($r) => (int) $r['id'] !== $id));
        $this->orderRows[$date][] = [
            'id' => (string) $id, 'order_name' => 'VI'.$id, 'device_id' => $eq, 'device_name' => 'SG'.$eq,
            'qty' => (string) array_sum(array_column($lines, 1)), 'money' => $money, 'good_money' => $money,
            'order_time' => $date.' '.$time, 'pay_succ_time' => $paidAt ?? ($date.' '.$time),
            'refer' => 'icb_antom', 'refer_type' => $pay === 'CARD' ? '2' : '6', 'order_status' => (string) $status,
            'order_product' => $products, 'transaction' => $pay,
        ];

        return $this;
    }

    public function priceGroups(): Collection
    {
        return collect($this->priceGroupRows)->map(fn ($r) => ChillerPriceGroupRow::fromApi($r));
    }

    public function signalStrength(string $deviceId): \App\Services\Citybox\DTO\ChillerSignal
    {
        if (! isset($this->signals[$deviceId])) {
            throw new CityboxApiException('Citybox openapi api/Openapi/signal_strength failed: code 400 — 设备不存在', 400);
        }

        return \App\Services\Citybox\DTO\ChillerSignal::fromApi($this->signals[$deviceId]);
    }

    public function submitRestockPhotos(string $deviceId, string $msgId, string $beforeUrl, string $afterUrl): void
    {
        if ($this->failPhotoSubmit !== null) {
            throw new CityboxApiException($this->failPhotoSubmit, 400);
        }
        $this->photoSubmits[] = ['device' => $deviceId, 'msgId' => $msgId, 'before' => $beforeUrl, 'after' => $afterUrl];
    }

    public function submitCount(RestockSession $session, StockCount $count): void
    {
        $this->submits[] = ['device' => $session->deviceId, 'msgId' => $session->msgId, 'rows' => $count->toApiRows()];
        // Their side refuses some writes (a stale msg_id, an API blip). Tests that
        // exercise the deferral path set this.
        if ($this->failSubmit !== null) {
            throw new CityboxApiException($this->failSubmit);
        }
        // Mirror their behaviour: the submitted numbers become the live stock.
        foreach ($count->realityStockByProductId as $pid => $qty) {
            foreach ($this->stock[$session->deviceId] ?? [] as $i => $row) {
                if ((int) $row['product_id'] === $pid) {
                    $this->stock[$session->deviceId][$i]['quantity'] = (string) $qty;
                }
            }
        }
        if ($this->afterSubmit) {
            ($this->afterSubmit)();
        }
    }
}
