<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\Vend;

/**
 * CityBox's OWN Pre-Stock Setup (shipping_product), read but never written.
 *
 * Until 2026-09-21 this class also overwrote the vend's ProductMapping from
 * that config, which made their portal the owner of our planogram — a template
 * switch there emptied a machine here (prod C5001, 2026-09-19). The mapping is
 * ours now (`ChillerChannelMap`); their config survives for exactly two jobs:
 *
 *  - the RECOGNITION CHECK: a SKU we map that their machine does not carry
 *    cannot be recognised by their AI, so ops must add it in OPS Pro;
 *  - reference numbers on the overview (their par, their layer).
 *
 * It also owns the channel-code scheme, which ops type by hand: <layer><position
 * 2 digits>, 101…599.
 */
class ChillerPlanogram
{
    public function __construct(private ChillerGateway $gateway, private CatalogSyncService $catalog) {}

    /**
     * Their Pre-Stock Setup for this vend, keyed by CityBox product id. Newly
     * seen SKUs are registered first (catalog upsert + mark1 product), so the
     * recognition check compares like with like.
     *
     * @return array<int,array{par:int,layer:int|null,price:int,active:int,name:string}>
     */
    public function theirConfig(Vend $vend): array
    {
        $lines = $this->gateway->restockConfig((string) $vend->citybox_equipment_id);
        $this->catalog->noteSeenOnDevice($lines);

        $out = [];
        foreach ($lines as $line) {
            $out[(int) $line->cityboxProductId] = [
                'par' => (int) $line->quantity,
                'layer' => $line->layer,
                'price' => $line->priceCents ?? 0,
                'active' => $line->effectivePriceCents() ?? ($line->priceCents ?? 0),
                'name' => $line->name,
            ];
        }

        return $out;
    }

    /**
     * Chiller channel code = <layer><position, two digits>: 101…199, 201…299 … 599.
     * Five layers, because every delivered unit has five (DeviceType::layerCount());
     * ops type these codes themselves since 2026-09-21, so 101–599 is also the
     * validation range on the mapping form (was 6 layers / 699 while the codes were
     * assigned automatically from their config).
     * Three digits (Brian, 2026-09-03) because a layer of small snacks can hold
     * more than nine SKUs and the vending scheme's <layer><position> two-digit
     * code stopped at 9. The first digit is still the layer, which is what the
     * index rows and the planogram view group by. Vending machines keep 10–69;
     * SyncVendChannels accepts this range only for smart_chiller vends.
     */
    public const MAX_LAYERS = 5;

    public const POSITIONS_PER_LAYER = 99;

    public const CODE_MIN = 101;

    public const CODE_MAX = 599;

    public static function channelCode(int $layer, int $position): int
    {
        return $layer * 100 + $position;
    }

    public static function layerOf(int $code): int
    {
        return intdiv($code, 100);
    }

    public static function positionOf(int $code): int
    {
        return $code % 100;
    }

    public static function isChillerCode(int $code): bool
    {
        return $code >= self::CODE_MIN && $code <= self::CODE_MAX && self::positionOf($code) >= 1;
    }
}
