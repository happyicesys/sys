<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Enums\Citybox\DeviceState;
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
        return collect($this->client->deviceProduct($deviceId)['goods'] ?? [])
            ->map(fn ($g) => ChillerStockLine::fromApi($g));
    }

    public function restockConfig(string $deviceId): Collection
    {
        return collect($this->client->shippingProduct($deviceId)['goods'] ?? [])
            ->map(fn ($g) => ChillerStockLine::fromApi($g));
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
