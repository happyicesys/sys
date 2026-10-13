<?php

namespace App\Services\Citybox\DTO;

use App\Services\Citybox\CityboxMoney;

/** One `order_product[]` line of an order_list row. */
final readonly class ChillerOrderLine
{
    public function __construct(
        public int $cityboxProductId,
        public string $name,
        public int $priceCents,
        public int $qty,
        public int $totalCents,
    ) {}

    public static function fromApi(array $l): self
    {
        $price = CityboxMoney::toCents(isset($l['price']) ? (string) $l['price'] : null) ?? 0;
        $qty = (int) ($l['qty'] ?? 0);

        return new self(
            cityboxProductId: (int) ($l['product_id'] ?? 0),
            name: (string) ($l['product_name'] ?? ''),
            priceCents: $price,
            qty: $qty,
            totalCents: CityboxMoney::toCents(isset($l['total_money']) ? (string) $l['total_money'] : null) ?? $price * $qty,
        );
    }
}
