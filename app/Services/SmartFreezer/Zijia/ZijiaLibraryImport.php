<?php

namespace App\Services\SmartFreezer\Zijia;

use App\Models\Product;
use App\Models\ZijiaSkuApplication;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Mirrors what a product was approved with in Zijia's vms4 portal into mark1 (Brian, 2026-10-06:
 * training happens in mark1 from now on, so the portal's work is pulled back once and kept in
 * step). Source: their public SKU library (§8 `query_list`) by the product's barcode — name,
 * brand, spec, category, package type, the package photo and the model photos.
 *
 * Each mirrored product gets one `zijia_sku_applications` row with `source = vms4`, status
 * approved, its photos COPIED to our DO Spaces (`sys/zijia-sku/{product}/vms4/…`) so mark1 never
 * depends on their links, and the library record kept as read. When their `updatedTime` moves (a
 * change made in vms4), the row is refreshed and the change logged. A product already approved
 * through mark1 with that barcode is not mirrored twice. Run by the 3-minute
 * `smart-freezer:zijia-barcode-sync` for every smart-freezer product with a barcode.
 */
class ZijiaLibraryImport
{
    public const OUTCOME_IMPORTED = 'imported';

    public const OUTCOME_UPDATED = 'updated';

    public const OUTCOME_UNCHANGED = 'unchanged';

    public const OUTCOME_NOT_IN_LIBRARY = 'not_in_library';

    public const OUTCOME_UNREACHABLE = 'unreachable';

    public const OUTCOME_MARK1_APPROVED = 'approved_in_mark1';

    public function __construct(
        private readonly ZijiaAlgorithmClient $client,
        private readonly ZijiaSkuApplicationService $applications,
    ) {}

    public function sync(Product $product): string
    {
        $barcode = trim((string) $product->barcode);
        if ($barcode === '') {
            return self::OUTCOME_NOT_IN_LIBRARY;
        }

        $page = $this->client->querySkus(1, 20, null, $barcode);
        if ($page === null) {
            return self::OUTCOME_UNREACHABLE;
        }
        $entry = collect($page['list'])->first(fn ($s) => (string) ($s['productCode'] ?? '') === $barcode);
        if ($entry === null) {
            return self::OUTCOME_NOT_IN_LIBRARY;
        }

        $byMark1 = ZijiaSkuApplication::query()->where('product_id', $product->id)->where('source', ZijiaSkuApplication::SOURCE_MARK1)
            ->where('status', ZijiaSkuApplication::STATUS_APPROVED)->where('product_code', $barcode)->exists();
        if ($byMark1) {
            return self::OUTCOME_MARK1_APPROVED;
        }

        $mirror = ZijiaSkuApplication::query()->where('product_id', $product->id)->where('source', ZijiaSkuApplication::SOURCE_VMS4)
            ->where('product_code', $barcode)->latest('id')->first();
        $updatedAt = self::time($entry['updatedTime'] ?? null);
        if ($mirror !== null) {
            $stored = (array) $mirror->library_entry;
            unset($stored['_copies']);
            $same = $updatedAt !== null
                ? $mirror->library_updated_at?->equalTo($updatedAt)
                : $stored == $entry; // no updatedTime from them: compare the record itself
            if ($same) {
                return self::OUTCOME_UNCHANGED;
            }
        }

        $copies = (array) (($mirror?->library_entry ?? [])['_copies'] ?? []);
        [$fields, $copies, $failed] = $this->fieldsFrom($product, $entry, $copies);
        $libraryEntry = $entry + ['_copies' => $copies];

        if ($mirror === null) {
            $mirror = ZijiaSkuApplication::query()->create($fields + [
                'product_id' => $product->id,
                'application_no' => 'vms4-'.($entry['sysSkuId'] ?? $barcode),
                'status' => ZijiaSkuApplication::STATUS_APPROVED,
                'source' => ZijiaSkuApplication::SOURCE_VMS4,
                'decided_at' => $updatedAt ?? now(),
                'library_entry' => $libraryEntry,
                'library_updated_at' => $updatedAt,
            ]);
            $this->applications->event($mirror, 'vms4.imported', null, [
                'library_entry' => $entry, 'photos_copied' => count($copies) - count($failed), 'photos_not_copied' => $failed ?: null,
            ], $failed ? 'warning' : 'info');

            return self::OUTCOME_IMPORTED;
        }

        $before = $mirror->only(['sku_name', 'brand_name', 'spec', 'category', 'package_type', 'product_code', 'package_image_url', 'model_pics', 'sys_sku_id']);
        $mirror->update($fields + ['library_entry' => $libraryEntry, 'library_updated_at' => $updatedAt]);
        $changed = array_keys(array_filter($fields, fn ($v, $k) => ($before[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
        $this->applications->event($mirror, 'vms4.updated', null, [
            'changed' => $changed ?: null, 'library_updated' => $entry['updatedTime'] ?? null, 'photos_not_copied' => $failed ?: null,
        ], $failed ? 'warning' : 'info');

        return self::OUTCOME_UPDATED;
    }

    /**
     * The application fields from a library entry, with every photo copied to our storage
     * (reusing copies already made). A photo that cannot be copied keeps Zijia's link.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: list<string>}
     */
    private function fieldsFrom(Product $product, array $entry, array $copies): array
    {
        $failed = [];
        $copy = function (?string $url) use ($product, &$copies, &$failed): ?string {
            if ($url === null || $url === '') {
                return null;
            }
            if (isset($copies[$url])) {
                return $copies[$url];
            }
            try {
                $response = Http::timeout(30)->get($url);
                if (! $response->successful() || $response->body() === '') {
                    throw new \RuntimeException("HTTP {$response->status()}");
                }
                $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
                $path = "sys/zijia-sku/{$product->id}/vms4/".sha1($url).'.'.preg_replace('/[^a-z0-9]/', '', $ext);
                Storage::put($path, $response->body(), 'public');

                return $copies[$url] = Storage::url($path);
            } catch (Throwable $e) {
                Log::warning('Zijia library photo not copied', ['url' => $url, 'error' => $e->getMessage()]);
                $failed[] = $url;

                return $url;
            }
        };

        $demo = $entry['sysDemoPic'] ?? null;
        $demo = is_string($demo) ? json_decode($demo, true) : $demo;
        $pics = [];
        foreach (ZijiaSkuApplicationService::ANGLES as $angle) {
            $urls = array_keys((array) (((array) $demo)[$angle] ?? []));
            $pics[$angle] = array_values(array_filter(array_map($copy, array_map('strval', $urls))));
        }

        return [[
            'sku_name' => $entry['skuName'] ?? $product->name,
            'brand_name' => $entry['brandName'] ?? null,
            'spec' => $entry['spec'] ?? null,
            'category' => isset($entry['category']) ? (int) $entry['category'] : null,
            'package_type' => isset($entry['packageType']) ? (int) $entry['packageType'] : null,
            'product_code' => (string) $entry['productCode'],
            'package_image_url' => $copy($entry['packageImageUrl'] ?? null),
            'model_pics' => $pics,
            'sys_sku_id' => isset($entry['sysSkuId']) ? (string) $entry['sysSkuId'] : null,
        ], $copies, $failed];
    }

    private static function time(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            // Their clock is China time, like every other timestamp they send.
            return Carbon::parse($value, (string) config('smart_freezer.zijia.algorithm.timezone', 'Asia/Shanghai'))->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
