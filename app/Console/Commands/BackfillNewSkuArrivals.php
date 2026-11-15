<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Models\VendProductArrival;
use App\Services\Menu\NewSkuBadge;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dates the SKUs the NEW-badge migration seeded as old (source "seed") from history, so a SKU
 * that really arrived on a machine in the last 60 days shows NEW for the rest of its 60 days.
 *
 * Arrival = the start of the SKU's current stay on the machine:
 *  1. Refill visits (ops_job_items with their slot list in ops_job_item_channels, the rule the
 *     User edit page's "SKU Start Date" uses): the first completed visit that carried it after
 *     a visit that did not. An absence of NewSkuBadge::DAYS or less does not count as leaving.
 *  2. No visit ever carried it: its first sale on the machine in the last 2 × DAYS days.
 *  3. Neither: left as seeded (old).
 *
 * Touches only rows still marked "seed", so live tracking is never overwritten. Dry run unless
 * --apply.
 */
class BackfillNewSkuArrivals extends Command
{
    protected $signature = 'menu:backfill-new-skus {--apply : Write the dates (default: report only)} {--vend= : One machine code}';

    protected $description = 'Date seeded vend SKUs from refill visits / sales so recent arrivals show the NEW badge';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $apply = (bool) $this->option('apply');
        $newSince = $now->subDays(NewSkuBadge::DAYS);

        $vends = Vend::query()
            ->whereNotNull('product_mapping_id')
            ->when($this->option('vend'), fn ($q, $code) => $q->bareCode($code))
            ->get(['id', 'code', 'product_mapping_id']);

        $dated = 0;
        $new = 0;
        $rows = [];

        foreach ($vends as $vend) {
            $seeded = VendProductArrival::query()
                ->where('vend_id', $vend->id)
                ->where('source', VendProductArrival::SOURCE_SEED)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if ($seeded === []) {
                continue;
            }

            $arrivals = self::arrivalsFromVisits((int) $vend->id, $seeded, $now);
            $missing = array_values(array_diff($seeded, array_keys($arrivals)));
            $arrivals += self::arrivalsFromSales((int) $vend->id, $missing, $now);

            foreach ($arrivals as $productId => $arrivedAt) {
                $dated++;
                $isNew = $arrivedAt->gt($newSince);
                if ($isNew) {
                    $new++;
                    $rows[] = [$vend->code, $productId, $arrivedAt->toDateTimeString(), $arrivedAt->addDays(NewSkuBadge::DAYS)->toDateString()];
                }
                if ($apply) {
                    VendProductArrival::query()
                        ->where('vend_id', $vend->id)
                        ->where('product_id', $productId)
                        ->where('source', VendProductArrival::SOURCE_SEED)
                        ->update([
                            'arrived_at' => $arrivedAt,
                            'source' => VendProductArrival::SOURCE_BACKFILL,
                            'updated_at' => $now,
                        ]);
                }
            }
        }

        if ($rows !== []) {
            $this->table(['Machine', 'Product id', 'Arrived', 'NEW until'], $rows);
        }
        $this->info(sprintf(
            '%s %d machine SKU(s) from history; %d of them NEW now.',
            $apply ? 'Dated' : 'Would date',
            $dated,
            $new,
        ));

        return self::SUCCESS;
    }

    /**
     * Start of each product's current stay from completed refill visits, merging absences of
     * DAYS or less. Products no visit ever carried are left out.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, CarbonImmutable>
     */
    public static function arrivalsFromVisits(int $vendId, array $productIds, CarbonImmutable $now): array
    {
        $visits = DB::table('ops_job_items')
            ->where('vend_id', $vendId)
            ->whereNotNull('completed_at')
            ->whereNull('cancelled_at')
            ->where('completed_at', '<=', $now)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('ops_job_item_channels')
                ->whereColumn('ops_job_item_channels.ops_job_item_id', 'ops_job_items.id'))
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get(['id', 'completed_at']);
        if ($visits->isEmpty()) {
            return [];
        }

        $carried = DB::table('ops_job_item_channels')
            ->whereIn('ops_job_item_id', $visits->pluck('id')->all())
            ->whereIn('product_id', $productIds)
            ->distinct()
            ->get(['ops_job_item_id', 'product_id'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('ops_job_item_id')->map(fn ($id) => (int) $id)->flip());

        $arrivals = [];
        foreach ($carried as $productId => $itemIds) {
            $start = null;       // start of the stay in progress
            $lastStart = null;   // start of the last stay, kept while the SKU is away
            $awaySince = null;   // first visit that did not carry it
            foreach ($visits as $visit) {
                $at = CarbonImmutable::parse($visit->completed_at);
                if ($itemIds->has((int) $visit->id)) {
                    if ($start === null) {
                        $start = ($lastStart !== null && $awaySince !== null && $awaySince->diffInDays($at) <= NewSkuBadge::DAYS)
                            ? $lastStart
                            : $at;
                    }
                    $awaySince = null;
                } elseif ($start !== null) {
                    $lastStart = $start;
                    $awaySince = $at;
                    $start = null;
                }
            }
            // Mapped now but the latest visit(s) did not carry it: the last stay still counts
            // when it ended DAYS or less ago; otherwise it is coming back, so no date here.
            if ($start === null && $lastStart !== null && $awaySince !== null && $awaySince->diffInDays($now) <= NewSkuBadge::DAYS) {
                $start = $lastStart;
            }
            if ($start !== null) {
                $arrivals[(int) $productId] = $start;
            }
        }

        return $arrivals;
    }

    /**
     * First sale of each product on the machine in the last 2 × DAYS days (the window that
     * decides "new now" and "had it in the DAYS before").
     *
     * @param  array<int, int>  $productIds
     * @return array<int, CarbonImmutable>
     */
    public static function arrivalsFromSales(int $vendId, array $productIds, CarbonImmutable $now): array
    {
        if ($productIds === []) {
            return [];
        }

        return DB::table('vend_transactions')
            ->where('vend_id', $vendId)
            ->where('created_at', '>=', $now->subDays(2 * NewSkuBadge::DAYS))
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, MIN(created_at) AS first_sale')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->product_id => CarbonImmutable::parse($row->first_sale)])
            ->all();
    }
}
