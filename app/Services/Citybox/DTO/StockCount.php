<?php

namespace App\Services\Citybox\DTO;

/**
 * The absolute per-product counts pushed by `device_stock_submit`.
 * Built by us from the driver's stock-in; validated here so a negative or
 * non-integer can never reach their API.
 */
final readonly class StockCount
{
    /** @param array<int,int> $realityStockByProductId citybox_product_id => absolute qty */
    private function __construct(public array $realityStockByProductId) {}

    /** @param array<int|string,int|string> $counts */
    public static function of(array $counts): self
    {
        $clean = [];
        foreach ($counts as $pid => $qty) {
            if (! is_numeric($pid) || (int) $pid <= 0) {
                throw new \InvalidArgumentException("Invalid citybox product id: {$pid}");
            }
            if (! is_numeric($qty) || (int) $qty < 0) {
                throw new \InvalidArgumentException("Invalid reality_stock for {$pid}: {$qty}");
            }
            $clean[(int) $pid] = (int) $qty;
        }
        if ($clean === []) {
            throw new \InvalidArgumentException('StockCount cannot be empty');
        }

        return new self($clean);
    }

    /** Their wire shape for `data` (before JSON encoding). */
    public function toApiRows(): array
    {
        $rows = [];
        foreach ($this->realityStockByProductId as $pid => $qty) {
            $rows[] = ['product_id' => $pid, 'reality_stock' => $qty];
        }

        return $rows;
    }
}
