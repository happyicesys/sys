<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\SellingPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who changed a product's Selling Price(s), and when (Brian, 2026-10-12).
 *
 * Every add and delete of a `selling_prices` row from the web is in `user_logs`
 * (App\Services\UserLogger, since 2026-07-18). The page never edits a price in place — a change is
 * a delete of the old row and an add of the new one for the same RP type — so a delete followed by
 * an add of that type reads as one change, old → new.
 *
 * The CURRENT row's author and previous price are also stored on the row (`created_by`,
 * `previous_amount`): written on save by ProductController, backfilled by
 * SellingPriceAuthorBackfillSeeder — both through this class. The history list is read from the log.
 */
final class SellingPriceHistory
{
    /**
     * Every change to this product's prices, newest first. Amounts in cents.
     *
     * @return list<array{at: string, by: ?string, user_id: ?int, type: int, type_name: string, kind: string, from: ?int, to: ?int}>
     */
    public function forProduct(Product $product): array
    {
        [$history] = $this->build($this->events($product->id));

        return array_reverse($history);
    }

    /**
     * The price this RP had before a new one is added now: the amount of its last delete, when
     * nothing has been added for that RP since. Null for an RP the product never had.
     */
    public function previousAmount(int $productId, int $type): ?int
    {
        $last = $this->events($productId)
            ->filter(fn ($e) => $this->typeOf($e) === $type)
            ->last();
        if ($last === null || $last->event !== 'deleted') {
            return null;
        }
        $changes = json_decode((string) $last->changes, true) ?: [];

        return isset($changes['amount']) ? (int) $changes['amount'] : null;
    }

    /**
     * Who added each selling price row and what it replaced, for every product in the log
     * (the backfill).
     *
     * @return array<int, array{user_id: ?int, previous_amount: ?int}> keyed by selling_prices.id
     */
    public function authorsOfAllRows(): array
    {
        $authors = [];
        foreach ($this->events(null)->groupBy(fn ($e) => $this->productOf($e)) as $events) {
            [, $added] = $this->build($events);
            foreach ($added as $sellingPriceId => $entry) {
                $authors[$sellingPriceId] = ['user_id' => $entry['user_id'], 'previous_amount' => $entry['from']];
            }
        }

        return $authors;
    }

    /** @return Collection<int, object> created/deleted SellingPrice log rows, oldest first */
    private function events(?int $productId): Collection
    {
        // user_logs_type_idx narrows to SellingPrice rows; product_id lives in the change set.
        return DB::table('user_logs')
            ->where('auditable_type', SellingPrice::class)
            ->whereIn('event', ['created', 'deleted'])
            ->when($productId !== null, fn ($q) => $q->whereRaw("JSON_EXTRACT(`changes`, '$.product_id') = ?", [$productId]))
            ->orderBy('id')
            ->get(['id', 'event', 'auditable_id', 'user_id', 'user_name', 'changes', 'created_at']);
    }

    /**
     * One product's log rows → its history (oldest first) and, per selling_prices.id added, the
     * line that added it.
     *
     * @param  Collection<int, object>  $events
     * @return array{0: list<array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function build(Collection $events): array
    {
        $history = [];
        $pending = [];   // type => index in $history of a "removed" not yet followed by an add
        $addedAt = [];   // selling_prices.id => index in $history
        foreach ($events as $event) {
            $changes = json_decode((string) $event->changes, true) ?: [];
            $type = $this->typeOf($event);
            $amount = isset($changes['amount']) ? (int) $changes['amount'] : null;
            $entry = [
                'at' => Carbon::parse($event->created_at)->toIso8601String(),
                'by' => $event->user_name,
                'user_id' => $event->user_id !== null ? (int) $event->user_id : null,
                'type' => $type,
                'type_name' => SellingPrice::TYPE_MAPPINGS[$type] ?? "RP{$type}",
            ];

            if ($event->event === 'deleted') {
                $history[] = $entry + ['kind' => 'removed', 'from' => $amount, 'to' => null];
                $pending[$type] = array_key_last($history);

                continue;
            }

            if (isset($pending[$type])) {
                // The delete earlier was the first half of this change: fold it into one line.
                $removed = $history[$pending[$type]];
                unset($history[$pending[$type]], $pending[$type]);
                $history[] = $entry + ['kind' => 'changed', 'from' => $removed['from'], 'to' => $amount];
            } else {
                $history[] = $entry + ['kind' => 'added', 'from' => null, 'to' => $amount];
            }
            $addedAt[(int) $event->auditable_id] = array_key_last($history);
        }

        return [array_values($history), array_map(fn (int $i) => $history[$i], $addedAt)];
    }

    private function typeOf(object $event): int
    {
        return (int) ((json_decode((string) $event->changes, true) ?: [])['type'] ?? 0);
    }

    private function productOf(object $event): int
    {
        return (int) ((json_decode((string) $event->changes, true) ?: [])['product_id'] ?? 0);
    }
}
