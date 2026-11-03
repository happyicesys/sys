<?php

namespace App\Services\SmartFreezer\Zijia;

use App\Models\ZijiaLibrarySku;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Keeps `zijia_library_skus` in step with the newest end of Zijia's SKU library (Brian, 2026-11-03).
 *
 * Their search is a substring match on the text we send, so searching by our product name cannot
 * find "Durian-family pack" from "Durian - family pack", let alone "Choc Eclair" from "Chocolate
 * Eclair". Reading what was registered and comparing here has neither problem. The library lists
 * oldest first and holds ~58k entries, so this walks back from the last page: to SINCE once a day,
 * which also drops entries they removed, and otherwise only until a whole page holds nothing new or
 * changed (3 calls on a normal run).
 */
class ZijiaLibraryMirror
{
    /** The first entry of ours is 1789349264435782 (2026-09-14); older ones are China packs. */
    public const SINCE_SYS_SKU_ID = 1789000000000000;

    private const PAGE_SIZE = 200;

    private const MAX_PAGES = 20;

    private const WALKED_BACK_CACHE_KEY = 'zijia-library-mirror:walked-back';

    public function __construct(private readonly ZijiaAlgorithmClient $client) {}

    /** @return array{new: int, changed: int, pages: int, reachable: bool} */
    public function refresh(): array
    {
        $result = ['new' => 0, 'changed' => 0, 'pages' => 0, 'reachable' => false];
        $probe = $this->client->querySkus(1, 1);
        if ($probe === null) {
            return $result;
        }
        $result['reachable'] = true;

        $page = (int) ceil($probe['totalCount'] / self::PAGE_SIZE);
        $mayStopEarly = Cache::has(self::WALKED_BACK_CACHE_KEY);
        $seen = [];
        while ($page >= 1 && $result['pages'] < self::MAX_PAGES) {
            $body = $this->client->querySkus($page, self::PAGE_SIZE);
            if ($body === null) {
                $result['reachable'] = false;
                break;
            }
            $result['pages']++;

            $rows = collect($body['list'])->filter(fn ($s) => filled($s['sysSkuId'] ?? null) && filled($s['productCode'] ?? null));
            $recent = $rows->filter(fn ($s) => (int) $s['sysSkuId'] >= self::SINCE_SYS_SKU_ID);
            $moved = $this->store($recent->all());
            $result['new'] += $moved['new'];
            $result['changed'] += $moved['changed'];
            foreach ($recent as $entry) {
                $seen[] = (string) $entry['sysSkuId'];
            }

            if ($recent->isEmpty()) {
                // The whole recent end was read: what it did not list, they removed.
                ZijiaLibrarySku::query()->whereNotIn('sys_sku_id', $seen)->delete();
                Cache::put(self::WALKED_BACK_CACHE_KEY, true, now()->addDay());
                break;
            }
            // A last page may hold a single entry, so a quiet page alone proves nothing until the
            // one before it is quiet too.
            if ($mayStopEarly && $moved['new'] === 0 && $moved['changed'] === 0 && $result['pages'] >= 2) {
                break;
            }
            $page--;
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array{new: int, changed: int}
     */
    private function store(array $entries): array
    {
        $moved = ['new' => 0, 'changed' => 0];
        if ($entries === []) {
            return $moved;
        }
        $known = ZijiaLibrarySku::query()
            ->whereIn('sys_sku_id', array_map(fn ($s) => (string) $s['sysSkuId'], $entries))
            ->get()->keyBy('sys_sku_id');

        foreach ($entries as $entry) {
            $id = (string) $entry['sysSkuId'];
            $fields = [
                'product_code' => mb_substr(trim((string) $entry['productCode']), 0, 64),
                'sku_name' => mb_substr((string) ($entry['skuName'] ?? ''), 0, 255),
                'name_key' => mb_substr(ZijiaBarcodeSync::normalise((string) ($entry['skuName'] ?? '')), 0, 255),
                'spec' => isset($entry['spec']) ? mb_substr((string) $entry['spec'], 0, 50) : null,
                'brand_name' => isset($entry['brandName']) ? mb_substr((string) $entry['brandName'], 0, 100) : null,
                'library_updated_at' => self::time($entry['updatedTime'] ?? null),
            ];
            $row = $known->get($id);
            if ($row === null) {
                ZijiaLibrarySku::query()->create($fields + ['sys_sku_id' => $id]);
                $moved['new']++;

                continue;
            }
            $row->fill($fields);
            if ($row->isDirty()) {
                $row->save();
                $moved['changed']++;
            }
        }

        return $moved;
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
