<?php

namespace App\Services\Citybox;

use App\Models\Vend;
use Illuminate\Support\Facades\Log;

/**
 * Syncs CityBox-Openapi state onto linked Smart Chiller vends.
 *
 * Every poll: one box_list (whole merchant fleet, one call) → online/status
 * per linked vend; then one device_product per linked vend → live stock
 * snapshot incl. `layer`, `price`, `active_price`. This crawl IS the stock
 * signal (Citybox sends us no order pushes — see OPENAPI_ANALYSIS §3b), so
 * it runs every 3 minutes; the fleet is 10 units, ~11 calls per sweep.
 *
 * Rules carried over from the retired apiThredDetail sync:
 *  - Never creates vends: an equipment_id we don't know is reported (import
 *    backlog), not imported.
 *  - Never flips is_active on their 禁运/撤机 status — ops decision; logged on
 *    transition only.
 *  - All anomaly logging is change-gated (this runs 1,440×/day).
 */
class CityboxOpenapiSync
{
    private const STATUS_RUNNING = 1;

    public function __construct(private OpenapiClient $client) {}

    /** Scheduler guard: any Smart Chiller linked at all? Indexed EXISTS, ~ms. */
    public static function hasLinkedVends(): bool
    {
        return Vend::where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)
            ->whereNotNull('citybox_equipment_id')
            ->exists();
    }

    /**
     * @return array{equipment:int,matched:int,missing:array,unknown:array,stock_errors:array}
     */
    public function syncAll(): array
    {
        $vends = Vend::where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)
            ->whereNotNull('citybox_equipment_id')
            ->get()
            ->keyBy('citybox_equipment_id');

        if ($vends->isEmpty()) {
            return ['equipment' => 0, 'matched' => 0, 'missing' => [], 'unknown' => [], 'stock_errors' => []];
        }

        $boxes = collect($this->client->boxList())->keyBy('equipment_id');

        $matched = 0;
        $stockErrors = [];
        foreach ($boxes as $equipmentId => $box) {
            $vend = $vends->get($equipmentId);
            if (! $vend) {
                continue;
            }

            // Stock is a separate call per device; a failure there must not
            // lose the status update we already have for this vend.
            $stock = null;
            try {
                $stock = $this->client->deviceProduct($equipmentId)['goods'] ?? [];
            } catch (\Throwable $e) {
                $stockErrors[$equipmentId] = $e->getMessage();
            }

            $this->apply($vend, $box, $stock);
            $matched++;
        }

        return [
            'equipment' => $boxes->count(),
            'matched' => $matched,
            // Ours but absent from their fleet — likely removed on their side.
            'missing' => $vends->keys()->diff($boxes->keys())->values()->all(),
            // Theirs but not ours — import backlog, never auto-created.
            'unknown' => $boxes->keys()->diff($vends->keys())->values()->all(),
            'stock_errors' => $stockErrors,
        ];
    }

    /** One-vend refresh (for a "Pull from Citybox" action). */
    public function pull(Vend $vend): Vend
    {
        $equipmentId = $vend->citybox_equipment_id;
        $box = collect($this->client->boxList(['equipment_id' => $equipmentId]))
            ->firstWhere('equipment_id', $equipmentId);

        if (! $box) {
            throw new \App\Exceptions\CityboxApiException("Citybox does not know equipment_id {$equipmentId}");
        }

        $this->apply($vend, $box, $this->client->deviceProduct($equipmentId)['goods'] ?? []);

        return $vend->refresh();
    }

    /** Map one box_list row (+ optional device_product goods) onto a vend. */
    private function apply(Vend $vend, array $box, ?array $goods): void
    {
        $status = (int) ($box['status'] ?? -1);
        $online = (int) ($box['equipment_online_status'] ?? 0) === 1;
        $previous = $vend->citybox_status_json ?? [];

        // null goods = the stock call failed this poll; keep the last snapshot
        // rather than pretending the chiller emptied.
        // Their product_id is a numeric STRING ("90340"). PHP silently casts
        // numeric-string array keys to int, and the JSON column cast round-trips
        // them back to string — a consumer doing $stock['90340'] vs $stock[90340]
        // would then hit or miss depending on which side wrote it. Pin to string
        // via a 'p' prefix so the key type is stable everywhere.
        $stock = $goods === null
            ? ($previous['stock'] ?? [])
            : collect($goods)->keyBy(fn ($g) => 'p'.$g['product_id'])->map(fn ($g) => [
                'product_id' => (string) ($g['product_id'] ?? ''),
                'name' => $g['name'] ?? null,
                'quantity' => (int) ($g['quantity'] ?? 0),
                'layer' => isset($g['layer']) ? (int) $g['layer'] : null,
                'price' => $g['price'] ?? null,
                'active_price' => $g['active_price'] ?? null,
                'thumbnail' => $g['thumbnailPic'] ?? null,
            ])->all();

        $vend->forceFill([
            'is_online' => $online,
            // The generic offline sweeper keys on last_updated_at — touch it
            // while their heartbeat says online so it doesn't flip a live
            // chiller back to offline between polls.
            'last_updated_at' => $online ? now() : $vend->last_updated_at,
            'citybox_synced_at' => now(),
            'citybox_status_json' => [
                'source' => 'openapi',
                'equipment_status' => $status,
                'equipment_status_str' => $box['equipment_status_str'] ?? null,
                'online' => $online,
                'device_type' => $box['type'] ?? null,
                'name' => $box['name'] ?? null,
                'stock' => $stock,
            ],
        ])->save();

        // Warn on the TRANSITION into non-running, not every minute it stays there.
        $previousStatus = $previous['equipment_status'] ?? null;
        if ($status !== self::STATUS_RUNNING && $status !== $previousStatus && $vend->is_active) {
            Log::warning('Citybox reports non-running status for active vend', [
                'vend_id' => $vend->id,
                'equipment_id' => $vend->citybox_equipment_id,
                'equipment_status' => $status,
                'previous_status' => $previousStatus,
            ]);
        }
    }
}
