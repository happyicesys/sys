<?php

namespace App\Console\Commands;

use App\Services\SmartFreezer\Zijia\ZijiaAlgorithmClient;
use Illuminate\Console\Command;

/**
 * Searches Zijia's algorithm SKU library (58k products on 2026-09-27) — the list the recognition
 * can name goods from. The `productCode` it prints is the barcode to put on the mark1 product:
 * the algorithm answers in those codes, and mark1 matches them back via `products.barcode`.
 */
class ZijiaAlgorithmSkus extends Command
{
    protected $signature = 'smart-freezer:zijia-skus
        {name? : part of the product name, e.g. 雪糕 or Magnum}
        {--code= : barcode prefix}
        {--page=1}
        {--size=20}';

    protected $description = 'Search Zijia\'s algorithm SKU library by name or barcode';

    public function handle(ZijiaAlgorithmClient $client): int
    {
        $page = $client->querySkus((int) $this->option('page'), (int) $this->option('size'), $this->argument('name'), $this->option('code'));
        if ($page === null) {
            $this->error('The SKU library did not answer.');

            return self::FAILURE;
        }

        $this->table(
            ['productCode (barcode)', 'name', 'spec', 'brand', 'sysSkuId', 'status'],
            array_map(fn (array $sku) => [
                $sku['productCode'] ?? '',
                $sku['skuName'] ?? '',
                $sku['spec'] ?? '',
                $sku['brandName'] ?? '',
                $sku['sysSkuId'] ?? '',
                $sku['status'] ?? '',
            ], $page['list']),
        );
        $this->line("page {$page['currentPage']} of {$page['totalPage']} ({$page['totalCount']} matches)");

        return self::SUCCESS;
    }
}
