<?php

namespace App\Services\StockCheck;

use App\Models\VendChannel;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Decides which channels of a machine a stock check asks the driver to count.
 * Pure: it is handed the machine's channels and returns the chosen ones, so
 * the rules can be unit-tested without a database and with a fixed picker.
 */
class StockCheckSampler
{
    /**
     * @param  (Closure(int[] $keys, int $count): int[])|null  $picker  how $count keys are
     *                                                                  chosen out of $keys; random by default, replaceable in tests
     */
    public function __construct(private ?Closure $picker = null)
    {
        $this->picker ??= fn (array $keys, int $count): array => Arr::random($keys, $count);
    }

    /**
     * Channels worth counting. A channel the system already shows as empty is
     * left out (Brian, 2026-09-19): a spot check cannot validate a sold-out
     * channel — what it can catch is "system says 1 left, driver finds 0", and
     * that needs a channel the system believes is stocked.
     *
     * @param  Collection<int, VendChannel>  $channels
     * @param  int[]|null  $productIds  null/empty = any product
     * @return Collection<int, VendChannel>
     */
    public function eligible(Collection $channels, ?array $productIds = null): Collection
    {
        $productIds = array_map('intval', $productIds ?? []);

        // toBase(): an Eloquent collection's only()/keys() speak MODEL ids, not
        // positions — draw() picks by position, so hand it a plain collection.
        return $channels
            ->toBase()
            ->filter(fn (VendChannel $c) => $c->is_active
                && (int) $c->capacity > 0
                && (int) $c->qty > 0
                && $c->product_id !== null
                && ($productIds === [] || in_array((int) $c->product_id, $productIds, true)))
            ->sortBy('code')
            ->values();
    }

    /**
     * @param  Collection<int, VendChannel>  $eligible
     * @return Collection<int, VendChannel> ordered by channel code
     */
    public function draw(Collection $eligible, bool $isRandom, ?int $sampleSize): Collection
    {
        $eligible = $eligible->toBase()->values();

        if (! $isRandom || $sampleSize === null || $sampleSize >= $eligible->count()) {
            return $eligible->sortBy('code')->values();
        }

        $keys = ($this->picker)($eligible->keys()->all(), max(1, $sampleSize));

        return $eligible->only($keys)->sortBy('code')->values();
    }
}
