<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Trait ExportOptimizationTrait
 *
 * Provides optimized export helpers to reduce memory usage and improve performance
 * for Excel/CSV exports across the application.
 */
trait ExportOptimizationTrait
{
    /**
     * Memory-efficient generator that yields items one by one.
     * Use this with FastExcel to avoid loading entire collection into memory.
     *
     * @param iterable $items
     * @return \Generator
     */
    protected function yieldOneByOne(iterable $items)
    {
        foreach ($items as $item) {
            yield $item;
        }
    }

    /**
     * Execute a query using cursor for memory-efficient iteration.
     * Returns a generator that can be used with FastExcel.
     *
     * @param Builder $query
     * @return \Generator
     */
    protected function exportWithCursor(Builder $query)
    {
        foreach ($query->cursor() as $item) {
            yield $item;
        }
    }

    /**
     * Stream export data with automatic chunking.
     * Useful for very large datasets that need to be processed in batches.
     *
     * @param Builder $query
     * @param int $chunkSize
     * @return \Generator
     */
    protected function streamExport(Builder $query, int $chunkSize = 1000)
    {
        $offset = 0;

        do {
            $items = $query->skip($offset)->take($chunkSize)->get();

            foreach ($items as $item) {
                yield $item;
            }

            $offset += $chunkSize;
        } while ($items->count() === $chunkSize);
    }

    /**
     * Estimate the number of rows that will be exported.
     * Used to determine if export should be queued.
     *
     * @param Builder $query
     * @return int
     */
    protected function estimateExportRowCount(Builder $query): int
    {
        return $query->count();
    }

    /**
     * Check if export should be queued based on estimated row count.
     *
     * @param int $rowCount
     * @param int|null $threshold
     * @return bool
     */
    protected function shouldQueueExport(int $rowCount, ?int $threshold = null): bool
    {
        $threshold = $threshold ?? config('exports.queue_threshold', 10000);
        return $rowCount > $threshold;
    }

    /**
     * Format a filename for export with timestamp.
     *
     * @param string $baseName
     * @param string $extension
     * @return string
     */
    protected function formatExportFilename(string $baseName, string $extension = 'xlsx'): string
    {
        return $baseName . '_' . now()->format('Ymd_His') . '.' . $extension;
    }

    /**
     * Get export configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getExportConfig(string $key, $default = null)
    {
        return config("exports.{$key}", $default);
    }
}
