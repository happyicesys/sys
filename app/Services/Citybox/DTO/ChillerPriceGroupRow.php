<?php

namespace App\Services\Citybox\DTO;

use App\Services\Citybox\CityboxMoney;

/** One `price_group_list` row: a product's price inside one group (one row per group × product). */
final readonly class ChillerPriceGroupRow
{
    public function __construct(
        public int $groupId,
        public string $groupName,
        public int $cityboxProductId,
        public string $productName,
        public int $priceCents,
        public bool $active,
        public ?string $imgUrl,
    ) {}

    public static function fromApi(array $r): self
    {
        return new self(
            groupId: (int) ($r['group_id'] ?? 0),
            groupName: trim((string) ($r['group_name'] ?? '')),
            cityboxProductId: (int) ($r['product_id'] ?? 0),
            productName: (string) ($r['product_name'] ?? ''),
            priceCents: CityboxMoney::toCents(isset($r['price']) ? (string) $r['price'] : null) ?? 0,
            active: (string) ($r['status'] ?? '1') === '1',
            imgUrl: isset($r['img_url']) && $r['img_url'] !== '' ? (string) $r['img_url'] : null,
        );
    }
}
