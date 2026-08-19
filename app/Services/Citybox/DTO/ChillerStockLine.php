<?php

namespace App\Services\Citybox\DTO;

use App\Services\Citybox\CityboxMoney;

/**
 * One `goods[]` row of `device_product` (live stock) — or of `shipping_product`
 * (restock config), where `quantity` means PAR and `activePriceCents` is null.
 * `cityboxProductId` is their numeric id (the ONLY stable SKU key; their
 * "product_id" SKU-code field is 0 for our tenant). Cast to int here so no
 * consumer ever sees "90340" vs 90340.
 */
final readonly class ChillerStockLine
{
    public function __construct(
        public int $cityboxProductId,
        public string $name,
        public int $quantity,
        public ?int $layer,
        public ?int $priceCents,
        public ?int $activePriceCents,
        public ?string $volume,
        public ?string $unit,
        public ?int $classId,
        public ?string $className,
        public ?string $thumbnailUrl,
    ) {}

    public static function fromApi(array $g): self
    {
        return new self(
            cityboxProductId: (int) ($g['product_id'] ?? 0),
            name: (string) ($g['name'] ?? ''),
            quantity: (int) ($g['quantity'] ?? 0),
            layer: isset($g['layer']) && is_numeric($g['layer']) ? (int) $g['layer'] : null,
            priceCents: self::money($g['price'] ?? null),
            activePriceCents: self::money($g['active_price'] ?? null),
            volume: isset($g['volume']) ? (string) $g['volume'] : null,
            unit: isset($g['unit']) ? (string) $g['unit'] : null,
            classId: isset($g['class_id']) && is_numeric($g['class_id']) ? (int) $g['class_id'] : null,
            className: isset($g['class_name']) ? (string) $g['class_name'] : null,
            thumbnailUrl: isset($g['thumbnailPic']) ? (string) $g['thumbnailPic'] : null,
        );
    }

    /** Effective selling price: promo if present, else list. */
    public function effectivePriceCents(): ?int
    {
        return $this->activePriceCents ?? $this->priceCents;
    }

    private static function money(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return CityboxMoney::toCents((string) $v);
        } catch (\Throwable) {
            return null;
        }
    }
}
