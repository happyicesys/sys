<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Safe MySQL index hints.
 *
 * A raw FORCE INDEX / USE INDEX hard-errors (SQLSTATE 1176) if the named index
 * doesn't exist — which happens on any DB whose migrations are behind (local
 * dev, a fresh environment, or a table where the index was later renamed/dropped).
 *
 * These helpers apply the hint ONLY if at least one of the given indexes is
 * actually present; otherwise they return the plain table so the query still
 * runs (the optimizer just picks its own plan). List indexes best-first — the
 * first present one is used. The present-index list is cached per table (1h) so
 * this adds no per-query overhead. Run `php artisan cache:clear` after adding an
 * index if you want the hint to take effect immediately.
 */
class IndexHint
{
    /** Return `table` FORCE INDEX (first-present) as a from-expression, else the plain table name. */
    public static function forceFrom(string $table, array $indexes)
    {
        $idx = self::firstExisting($table, $indexes);
        return $idx ? DB::raw("`{$table}` FORCE INDEX ({$idx})") : $table;
    }

    /** Return `table` USE INDEX (first-present) as a from-expression, else the plain table name. */
    public static function useFrom(string $table, array $indexes)
    {
        $idx = self::firstExisting($table, $indexes);
        return $idx ? DB::raw("`{$table}` USE INDEX ({$idx})") : $table;
    }

    /** Return " FORCE INDEX (idx)" (or " USE INDEX (idx)") for raw SQL, or "" if none present. */
    public static function clause(string $table, array $indexes, string $type = 'FORCE'): string
    {
        $idx = self::firstExisting($table, $indexes);
        return $idx ? " {$type} INDEX ({$idx})" : '';
    }

    private static function firstExisting(string $table, array $indexes): ?string
    {
        $present = Cache::remember("index_names:{$table}", now()->addHour(), function () use ($table) {
            try {
                return collect(DB::select("SHOW INDEX FROM `{$table}`"))
                    ->pluck('Key_name')->unique()->values()->all();
            } catch (\Throwable $e) {
                return [];
            }
        });

        foreach ($indexes as $i) {
            if (in_array($i, $present, true)) {
                return $i;
            }
        }

        return null;
    }
}
