<?php

namespace App\Enums;

/**
 * Where a product's "Qty in Warehouse" comes from (design §8.1).
 *  cms     the CMS API, keyed by products.code — the SG vending fleet today
 *  ledger  mark1's own product_movements ledger (manual incoming + picks) —
 *          CityBox products (born ledger) and any product with no CMS presence
 * Not Citybox-namespaced: the source of the warehouse number and the source of
 * the product are different questions.
 */
enum WarehouseQtySource: string
{
    case Cms = 'cms';
    case Ledger = 'ledger';

    public function label(): string
    {
        return match ($this) {
            self::Cms => 'CMS API (pulled)',
            self::Ledger => 'Manual (self-system ledger)',
        };
    }
}
