<?php

namespace App\Services\Stock;

use App\Services\CmsService;
use Illuminate\Support\Facades\Cache;

/**
 * The CMS warehouse qty ([product code => available pcs]) that the nightly
 * stock count stamps on every machine's items.
 *
 * It is ONE fleet-wide list, so the dispatching command fetches it once (with
 * retries) and every SaveStockCount job reads it from the cache — it used to be
 * fetched once per machine, ~400 calls a night. A job that finds no cached list
 * tries CMS once itself, so a CMS that comes back mid-run still fills the rest.
 *
 * A failed fetch is never cached and never fatal: null means "warehouse qty
 * unknown", and the caller still saves the machine qty. On 2026-09-21 a CMS
 * connect timeout at 23:59 failed every job before it wrote a single item, and
 * that day's machine stock was lost for good — it is read live from
 * vend_channels and can never be rebuilt afterwards.
 */
class StockCountWarehouseQty
{
    public const CACHE_TTL_SECONDS = 7200;

    public function __construct(private CmsService $cms) {}

    /**
     * Fetch ahead of dispatch. Returns whether a list is now cached for $date.
     */
    public function prime(string $date, int $tries = 3, int $sleepMs = 5000): bool
    {
        $list = $this->cms->fetchQtyAvailableByCode($tries, $sleepMs);
        if ($list === null) {
            return false;
        }

        Cache::put(self::cacheKey($date), $list, self::CACHE_TTL_SECONDS);

        return true;
    }

    /**
     * The cached list for $date, else one attempt at CMS; null when unreachable.
     */
    public function forDate(string $date): ?array
    {
        $cached = Cache::get(self::cacheKey($date));
        if (is_array($cached)) {
            return $cached;
        }

        $list = $this->cms->fetchQtyAvailableByCode();
        if ($list !== null) {
            Cache::put(self::cacheKey($date), $list, self::CACHE_TTL_SECONDS);
        }

        return $list;
    }

    public static function cacheKey(string $date): string
    {
        return 'stock-count:cms-warehouse-qty:'.$date;
    }
}
