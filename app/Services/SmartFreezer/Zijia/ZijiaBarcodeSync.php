<?php

namespace App\Services\SmartFreezer\Zijia;

use App\Models\Product;
use App\Models\Vend;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fills in `products.barcode` from Zijia's SKU library for products on a smart freezer's planogram
 * that have none (Brian, 2026-10-05). The AI only looks for products whose barcode mark1 sends in
 * `goodsList`, so a product without one is "Cannot judge" and charges the cart.
 *
 * Zijia gives no approval callback yet and their library entry carries no link to our product
 * (only name, spec, barcode, photos), and the library is shared by all their customers. So a
 * barcode is written ONLY when exactly one library product has exactly our product's name
 * (case and punctuation aside) — a wrong barcode would make the AI name the wrong product and
 * charge wrongly. An existing barcode is never touched. Anything else is left for a person and
 * listed in the 08:30 health email (`lastResults`).
 */
class ZijiaBarcodeSync
{
    private const RESULT_CACHE_KEY = 'zijia-barcode-sync:results';

    public function __construct(
        private readonly ZijiaAlgorithmClient $client,
        private readonly ZijiaLibraryImport $import,
    ) {}

    /**
     * @return array<int, array{product_id: int, name: string, outcome: string, barcode?: string, candidates?: list<string>}>
     */
    public function run(): array
    {
        $results = [];
        foreach ($this->productsWithoutBarcode() as $product) {
            $results[$product->id] = $this->syncOne($product);
        }
        Cache::put(self::RESULT_CACHE_KEY, $results, now()->addDay());

        return $results;
    }

    /**
     * Mirrors every smart-freezer product WITH a barcode from Zijia's library into mark1 — what it
     * was approved with in vms4, photos included — and picks up changes made there since
     * (ZijiaLibraryImport). Cheap when nothing changed: one library query per product.
     *
     * @return array<int, array{product_id: int, name: string, outcome: string}>
     */
    public function importApproved(): array
    {
        $results = [];
        $products = Product::withoutGlobalScopes()->whereIn('id', $this->freezerProductIds())
            ->whereNotNull('barcode')->where('barcode', '!=', '')
            ->orderBy('id')->get(['id', 'code', 'name', 'barcode']);
        foreach ($products as $product) {
            // One product's failure (a photo host, bad data) never stops the others.
            try {
                $outcome = $this->import->sync($product);
            } catch (\Throwable $e) {
                report($e);
                $outcome = 'error: '.mb_substr($e->getMessage(), 0, 120);
            }
            $results[$product->id] = ['product_id' => $product->id, 'name' => $product->name, 'outcome' => $outcome];
        }

        return $results;
    }

    /**
     * What the last run found for products still without a barcode, for the health email.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lastResults(): array
    {
        return array_filter((array) Cache::get(self::RESULT_CACHE_KEY, []), fn ($r) => ($r['outcome'] ?? '') !== 'set');
    }

    /** @return array{product_id: int, name: string, outcome: string, barcode?: string, candidates?: list<string>} */
    private function syncOne(Product $product): array
    {
        $base = ['product_id' => $product->id, 'name' => $product->name];
        $page = $this->client->querySkus(1, 50, $product->name);
        if ($page === null) {
            return $base + ['outcome' => 'unreachable'];
        }

        $want = self::normalise($product->name);
        $codes = collect($page['list'])
            ->filter(fn ($sku) => self::normalise((string) ($sku['skuName'] ?? '')) === $want)
            ->pluck('productCode')->filter()->map(fn ($c) => (string) $c)->unique()->values();

        if ($codes->isEmpty()) {
            return $base + ['outcome' => 'not_in_library'];
        }
        if ($codes->count() > 1) {
            return $base + ['outcome' => 'ambiguous', 'candidates' => $codes->all()];
        }

        $barcode = $codes->first();
        // Re-read under the write: a person may have set one since the query started.
        $updated = Product::withoutGlobalScopes()->whereKey($product->id)
            ->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''))
            ->update(['barcode' => $barcode, 'updated_at' => now()]);
        if ($updated === 0) {
            return $base + ['outcome' => 'already_set'];
        }

        // Console writes are not audited by UserLogger; record this one by hand so the product's
        // history says who set it.
        DB::table('user_logs')->insert([
            'user_id' => null,
            'user_name' => 'Zijia sync',
            'event' => 'updated',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'changes' => json_encode(['barcode' => [$product->barcode, $barcode]]),
            'source' => 'zijia-sync',
            'ip' => null,
            'url' => 'smart-freezer:zijia-barcode-sync',
            'created_at' => now(),
        ]);
        Log::info('Zijia barcode synced', ['product_id' => $product->id, 'name' => $product->name, 'barcode' => $barcode]);

        return $base + ['outcome' => 'set', 'barcode' => $barcode];
    }

    /** @return \Illuminate\Support\Collection<int, Product> products on a smart freezer's planogram with no barcode */
    private function productsWithoutBarcode()
    {
        return Product::withoutGlobalScopes()->whereIn('id', $this->freezerProductIds())
            ->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''))
            ->whereNotNull('name')->where('name', '!=', '')
            ->orderBy('id')
            ->get(['id', 'name', 'barcode']);
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function freezerProductIds()
    {
        return DB::table('vend_channels')
            ->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vends.machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)
            ->whereNotNull('vend_channels.product_id')
            ->distinct()
            ->pluck('vend_channels.product_id');
    }

    /** "Basque Cheesecake - Chocolate" and "basque cheesecake chocolate" are the same name. */
    public static function normalise(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}]+/u', ' ', mb_strtolower($name)));
    }
}
