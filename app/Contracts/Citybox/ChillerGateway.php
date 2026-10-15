<?php

namespace App\Contracts\Citybox;

use App\Enums\Citybox\DeviceState;
use App\Services\Citybox\DTO\ChillerCatalogItem;
use App\Services\Citybox\DTO\ChillerDevice;
use App\Services\Citybox\DTO\ChillerOrder;
use App\Services\Citybox\DTO\ChillerPriceGroupRow;
use App\Services\Citybox\DTO\ChillerStockLine;
use App\Services\Citybox\DTO\RestockSession;
use App\Services\Citybox\DTO\StockCount;
use Illuminate\Support\Collection;

/**
 * What a smart-chiller supplier can do for us. mark1's ops/inventory layer
 * depends on THIS, never on a concrete API client, so a test fake — or a
 * second chiller brand — plugs in without the ops code noticing.
 *
 * Every method throws \App\Exceptions\CityboxApiException on transport,
 * auth or business refusal; none returns raw arrays.
 */
interface ChillerGateway
{
    /** @return Collection<int, ChillerDevice> the merchant's fleet */
    public function listDevices(array $filters = []): Collection;

    /** @return Collection<int, ChillerStockLine> LIVE stock; `quantity` = on-hand */
    public function deviceStock(string $deviceId): Collection;

    /** @return Collection<int, ChillerStockLine> restock config; `quantity` = PAR */
    public function restockConfig(string $deviceId): Collection;

    /** @return Collection<int, ChillerCatalogItem> merchant catalog */
    public function catalog(array $filters = []): Collection;

    public function deviceState(string $deviceId): DeviceState;

    /** Ops door-open (NOT a consumer session). $operatorRef ≤ 30 chars, attributed in their logs. */
    public function openForRestock(string $deviceId, string $operatorRef): RestockSession;

    /** Overwrite the device's stock with absolute counts. Requires the session's msg_id. */
    public function submitCount(RestockSession $session, StockCount $count): void;

    /** @return Collection<int, ChillerOrder> the orders of one ORDER day (Y-m-d), paid and refund statuses */
    public function orders(string $date): Collection;

    /** @return Collection<int, ChillerPriceGroupRow> every active price group, one row per group × product */
    public function priceGroups(): Collection;

    /** One device's signal reading, on the 5-bar scale (ChillerSignal). */
    public function signalStrength(string $deviceId): \App\Services\Citybox\DTO\ChillerSignal;

    /** One restock visit's before + after photo URLs, tied to that visit's door-open msg_id. */
    public function submitRestockPhotos(string $deviceId, string $msgId, string $beforeUrl, string $afterUrl): void;
}
