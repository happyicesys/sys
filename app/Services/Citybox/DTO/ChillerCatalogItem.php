<?php

namespace App\Services\Citybox\DTO;

/** One row of `product_list` (merchant catalog): identity + images only. */
final readonly class ChillerCatalogItem
{
    public function __construct(
        public int $cityboxProductId,
        public string $name,
        public ?string $skuCode,     // their product_id field — "0"/empty for us today; keep in case they populate it
        public ?string $imgUrl,
        /** @var string[] */
        public array $visionImgs,
    ) {}

    public static function fromApi(array $r): self
    {
        $sku = isset($r['product_id']) ? (string) $r['product_id'] : null;

        return new self(
            cityboxProductId: (int) ($r['id'] ?? 0),
            name: (string) ($r['product_name'] ?? ''),
            skuCode: ($sku === null || $sku === '' || $sku === '0') ? null : $sku,
            imgUrl: isset($r['img_url']) && $r['img_url'] !== '' ? (string) $r['img_url'] : null,
            visionImgs: array_values(array_filter([
                $r['vision_img'] ?? null, $r['vision_img2'] ?? null,
                $r['vision_img3'] ?? null, $r['vision_img4'] ?? null,
            ], fn ($u) => is_string($u) && $u !== '')),
        );
    }
}
