<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Enums\Citybox\DeviceState;
use App\Exceptions\CityboxApiException;
use App\Services\Citybox\DTO\ChillerCatalogItem;
use App\Services\Citybox\DTO\ChillerDevice;
use App\Services\Citybox\DTO\ChillerStockLine;
use App\Services\Citybox\DTO\RestockSession;
use App\Services\Citybox\DTO\StockCount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The CityBox implementation of ChillerGateway. Composes the proven
 * OpenapiClient (HTTP, signing, token cache — unchanged) and does exactly
 * one extra job: turn their arrays into typed value objects. Business
 * services never see an array from here.
 */
class CityboxOpenapiGateway implements ChillerGateway
{
    public function __construct(private OpenapiClient $client) {}

    public function listDevices(array $filters = []): Collection
    {
        return collect($this->client->boxList($filters))->map(fn ($r) => ChillerDevice::fromApi($r));
    }

    public function deviceStock(string $deviceId): Collection
    {
        return collect($this->goodsOrEmpty(fn () => $this->client->deviceProduct($deviceId)))
            ->map(fn ($g) => ChillerStockLine::fromApi($g));
    }

    public function restockConfig(string $deviceId): Collection
    {
        return collect($this->goodsOrEmpty(fn () => $this->client->shippingProduct($deviceId)))
            ->map(fn ($g) => ChillerStockLine::fromApi($g));
    }

    /**
     * A device with NOTHING configured in their portal (Pre-Stock Setup empty)
     * answers device_product / shipping_product with code 400 "此设备没有商品"
     * (this device has no products) — prod vend 10001 / Singapore1, 2026-08-20.
     * That is a legitimate state, not a failure: report an empty list so the
     * poll logs "0 products" instead of an error every 3 min. Any other 400
     * still throws.
     */
    private function goodsOrEmpty(callable $call): array
    {
        try {
            return $call()['goods'] ?? [];
        } catch (CityboxApiException $e) {
            if ($e->apiCode === 400 && str_contains($e->getMessage(), '此设备没有商品')) {
                return [];
            }
            throw $e;
        }
    }

    public function catalog(array $filters = []): Collection
    {
        return collect($this->client->productList($filters))->map(fn ($r) => ChillerCatalogItem::fromApi($r));
    }

    public function deviceState(string $deviceId): DeviceState
    {
        return DeviceState::fromApi($this->client->deviceStatus($deviceId)['code'] ?? null);
    }

    public function openForRestock(string $deviceId, string $operatorRef): RestockSession
    {
        $body = $this->client->zyyLsOpenDoor($deviceId, $operatorRef);

        return RestockSession::fromApi($body, CarbonImmutable::now());
    }

    public function submitCount(RestockSession $session, StockCount $count): void
    {
        $this->client->deviceStockSubmit($session->deviceId, $session->msgId, $count->toApiRows());
    }
}
