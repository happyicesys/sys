<?php

namespace App\Support;

use App\Models\VendTransaction;

/**
 * A Smart Freezer sale made in Testing Mode (Brian, 2026-11-03; freezer app v45).
 *
 * A technician turns Testing Mode on in the freezer's Freezer care panel: every product then sells
 * at UNIT_PRICE_CENTS, and the TRADE carries `"TEST":1` (Mark1SaleUploader). Such a sale is booked
 * in full like any other — Sales Transactions, AI recognition, the T05 charge from the AI verdict —
 * with two differences only:
 *
 *  - **no stock moves** (FreezerStockLedger::unitsDispensed reads nothing from it, so neither the
 *    live ledger nor `freezer:rebuild-stock` counts it);
 *  - **the AI verdict prices every unit at UNIT_PRICE_CENTS**, an extra item of a product not in the
 *    cart included (CardPaymentService::decideForAi, FreezerSaleAudit::rewriteBasket) — otherwise a
 *    took_more of another SKU would be charged at the shelf price.
 *
 * The flag lives in the stored frame (`vend_transaction_json->TEST`), the way SFREF does: no column
 * on vend_transactions.
 */
final class FreezerTestSale
{
    /** What the freezer charges per item in Testing Mode (TestingMode.UNIT_PRICE in the app). */
    public const UNIT_PRICE_CENTS = 10;

    public static function is(?VendTransaction $sale): bool
    {
        return $sale !== null && self::frameIsTest($sale->vend_transaction_json);
    }

    /** @param  array<string, mixed>|string|null  $frame */
    public static function frameIsTest($frame): bool
    {
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }

        return is_array($frame) && ! empty($frame['TEST']);
    }
}
