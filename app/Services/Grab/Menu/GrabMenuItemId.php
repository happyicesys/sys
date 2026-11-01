<?php

namespace App\Services\Grab\Menu;

/**
 * The Grab item id of a machine-menu product: "P" + products.id. A template item id is a
 * bare number (delivery_product_mapping_items.id), so an order carrying either kind is
 * read the right way while stores switch over.
 */
final class GrabMenuItemId
{
    const PREFIX = 'P';

    public static function forProduct(int $productId): string
    {
        return self::PREFIX.$productId;
    }

    // The product id in a machine-menu item id; null for a template item id.
    public static function productId(string|int|null $itemId): ?int
    {
        $itemId = (string) $itemId;

        return preg_match('/^'.self::PREFIX.'(\d+)$/', $itemId, $match) ? (int) $match[1] : null;
    }
}
