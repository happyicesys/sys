<?php

namespace App\Services\Citybox;

use App\Exceptions\CityboxApiException;
use App\Models\Vend;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Syncs Citybox supplier state onto the linked mark1 vends.
 *
 * Two entry points:
 *  - syncAll()   — the every-minute poller: one paginated sweep of their
 *                  equipment + stock endpoints, applied to every linked vend.
 *  - pull(Vend)  — the on-demand "Pull from Citybox" action on a single vend
 *                  (Vend create/edit screen).
 *
 * What it deliberately does NOT do:
 *  - Never creates vends (an unknown equipment_id is reported, not imported —
 *    creation is a human decision on the import screen).
 *  - Never flips is_active when Citybox reports 禁运/撤机 (0/98/99) — that is
 *    an ops decision; the state is recorded + logged for the alert layer.
 *  - Stock deltas between polls are recorded as a provisional signal ONLY.
 *    They are NOT sales and never become vend_transactions — no money data,
 *    can't distinguish sale/adjustment/topup. Real sales wait on the
 *    transactions endpoint (citybox/API_ANALYSIS.md §2, request #1).
 */
class CityboxEquipmentSync
{
    // Their equipment_status values (doc: 0 禁运, 1 启运, 98 撤机中, 99 已撤机).
    private const STATUS_RUNNING = 1;

    // Pagination safety cap: 50 pages × 100 rows. If a fleet ever exceeds this
    // the loop logs loudly instead of silently truncating.
    private const MAX_PAGES = 50;

    public function __construct(private CityboxClient $client) {}

    /**
     * The scheduler guard: is there anything to poll at all?
     * Hits the UNIQUE index on citybox_equipment_id — a few ms, safe to run
     * every minute even though the poll itself only exists once chillers do.
     */
    public static function hasLinkedVends(): bool
    {
        return Vend::whereNotNull('citybox_equipment_id')->exists();
    }

    /**
     * Poll Citybox once and apply to every linked vend.
     *
     * @return array{equipment:int,matched:int,missing:array,unknown:array}
     */
    public function syncAll(): array
    {
        $vends = Vend::whereNotNull('citybox_equipment_id')
            ->get()
            ->keyBy('citybox_equipment_id');

        if ($vends->isEmpty()) {
            return ['equipment' => 0, 'matched' => 0, 'missing' => [], 'unknown' => []];
        }

        $equipment = $this->fetchAllPages(
            fn (int $page) => $this->client->getEquipment(page: $page, limit: 100),
        )->keyBy('equipment_id');

        $stockByEquipment = $this->fetchAllPages(
            fn (int $page) => $this->client->getEquipmentProducts(null, $page, 100),
        )->groupBy('equipment_id');

        $matched = 0;
        foreach ($equipment as $equipmentId => $row) {
            if ($vend = $vends->get($equipmentId)) {
                $this->apply($vend, $row, $stockByEquipment->get($equipmentId));
                $matched++;
            }
        }

        // Ours but absent from their fleet list — likely removed on their side.
        // Logged by the caller on CHANGE only (this runs every minute).
        $missing = $vends->keys()->diff($equipment->keys())->values()->all();

        // Theirs but not ours — import-screen backlog, never auto-created.
        $unknown = $equipment->keys()->diff($vends->keys())->values()->all();

        return [
            'equipment' => $equipment->count(),
            'matched' => $matched,
            'missing' => $missing,
            'unknown' => $unknown,
        ];
    }

    /**
     * One-vend refresh for the "Pull from Citybox" button. Throws when their
     * API does not know the vend's citybox_equipment_id.
     */
    public function pull(Vend $vend): Vend
    {
        $equipmentId = $vend->citybox_equipment_id;
        if (! $equipmentId) {
            throw new CityboxApiException('Vend has no citybox_equipment_id set');
        }

        // Their equipment_id filter is fuzzy (模糊查询) — re-match exactly.
        $data = $this->client->getEquipment(['equipment_id' => $equipmentId], page: 1, limit: 100);
        $row = collect($data['list'] ?? [])->firstWhere('equipment_id', $equipmentId);

        if (! $row) {
            throw new CityboxApiException("Citybox does not know equipment_id {$equipmentId}");
        }

        // Targeted single-device query: an empty list genuinely means "no
        // products on this chiller" — pass it through so stale stock clears.
        // (Only the fleet-wide sweep treats a missing device as "no data".)
        $stock = collect($this->client->getEquipmentProducts($equipmentId)['list'] ?? []);
        $this->apply($vend, $row, $stock);

        return $vend->refresh();
    }

    /** Map one supplier equipment row (+ optional stock rows) onto a vend. */
    private function apply(Vend $vend, array $row, ?Collection $stockRows): void
    {
        $status = (int) ($row['equipment_status'] ?? -1);
        $online = (int) ($row['equipment_online_status'] ?? 0) === 1;

        $previous = $vend->citybox_status_json ?? [];

        // Snapshot stock keyed by their product row id; null = endpoint gave
        // us nothing for this device this poll (keep the previous snapshot
        // rather than pretending the chiller emptied).
        $stock = $stockRows?->keyBy('id')->map(fn ($r) => [
            'product_name' => $r['product_name'] ?? null,
            'stock' => (int) ($r['stock'] ?? 0),
            'pre_qty' => (int) ($r['pre_qty'] ?? 0),
        ])->all() ?? ($previous['stock'] ?? []);

        $changes = $this->stockChanges($previous['stock'] ?? [], $stock);

        $vend->forceFill([
            'is_online' => $online,
            // The generic 5-min offline sweeper keys on last_updated_at; touch
            // it while their heartbeat says online so the sweeper doesn't flip
            // a live chiller back to offline between polls.
            'last_updated_at' => $online ? now() : $vend->last_updated_at,
            'citybox_synced_at' => now(),
            'citybox_status_json' => [
                'equipment_status' => $status,
                'equipment_status_str' => $row['equipment_status_str'] ?? null,
                'online' => $online,
                'heartbeat_last_offline' => $row['heartbeat_last_offline'] ?? null,
                'heartbeat_last_recovery' => $row['heartbeat_last_recovery'] ?? null,
                'stock' => $stock,
                'stock_changes' => $changes ?: null,
            ],
        ])->save();

        // Warn on the TRANSITION into a non-running status, not on every
        // one-minute poll while it stays there — a paused chiller must not
        // write 1,440 identical warnings a day.
        $previousStatus = $previous['equipment_status'] ?? null;
        if ($status !== self::STATUS_RUNNING && $status !== $previousStatus && $vend->is_active) {
            // Recorded, logged, NOT auto-deactivated — see class doc.
            Log::warning('Citybox reports non-running status for active vend', [
                'vend_id' => $vend->id,
                'equipment_id' => $vend->citybox_equipment_id,
                'equipment_status' => $status,
                'previous_status' => $previousStatus,
            ]);
        }

        if ($changes) {
            Log::info('Citybox stock delta (provisional signal, not sales)', [
                'vend_id' => $vend->id,
                'equipment_id' => $vend->citybox_equipment_id,
                'changes' => $changes,
            ]);
        }
    }

    /** Per-product qty movement between two snapshots: [{id, product_name, from, to}]. */
    private function stockChanges(array $previous, array $current): array
    {
        $changes = [];
        foreach ($current as $id => $row) {
            $before = $previous[$id]['stock'] ?? null;
            if ($before !== null && $before !== $row['stock']) {
                $changes[] = [
                    'id' => $id,
                    'product_name' => $row['product_name'],
                    'from' => $before,
                    'to' => $row['stock'],
                ];
            }
        }

        return $changes;
    }

    /** Drain a paginated endpoint's `list` across pages, loudly capped. */
    private function fetchAllPages(callable $fetch): Collection
    {
        $rows = collect();
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $data = $fetch($page);
            $list = collect($data['list'] ?? []);
            $rows = $rows->concat($list);

            $lastPage = (int) ($data['last_page'] ?? $page);
            if ($page >= $lastPage || $list->isEmpty()) {
                return $rows;
            }
        }

        Log::error('Citybox pagination hit the safety cap — results are TRUNCATED', ['pages' => self::MAX_PAGES]);

        return $rows;
    }
}
