<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxDevice;
use App\Services\Citybox\DTO\ChillerDevice;
use Illuminate\Support\Collection;

/**
 * The only writer of `citybox_devices`. Turns each `box_list` result into ONE
 * upsert statement for the whole fleet (~10 rows/min — never a query per
 * device), and serves the fleet back as DTOs so callers stop needing a live
 * API call or a cache to know which devices exist.
 *
 * Freshness rule: the poller refreshes every minute while any chiller is
 * linked; before the first chiller exists nothing polls, so readers ask for
 * `fresh()` when the newest row is older than FRESH_MINUTES (or the table is
 * empty). Rows are never deleted — a device that leaves `box_list` keeps its
 * row with in_fleet = false and is no longer offered.
 */
class CityboxDeviceRegistry
{
    /** Newest row older than this ⇒ readers pull the fleet before answering. */
    public const FRESH_MINUTES = 5;

    public function __construct(private ChillerGateway $gateway) {}

    /**
     * Persist one fleet listing. Returns the same DTOs keyed by equipment id so
     * the caller (DeviceSyncService) never re-reads what it just wrote.
     *
     * @param  Collection<int,ChillerDevice>  $devices
     * @return Collection<string,ChillerDevice>
     */
    public function record(Collection $devices, bool $complete = true): Collection
    {
        $keyed = $devices->keyBy(fn (ChillerDevice $d) => $d->equipmentId)
            ->filter(fn (ChillerDevice $d, string $id) => $id !== '');

        if ($keyed->isNotEmpty()) {
            $now = now();
            $rows = $keyed->map(fn (ChillerDevice $d) => $d->toRegistryRow() + [
                'in_fleet' => true, 'first_seen_at' => $now, 'last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ])->values()->all();

            // first_seen_at / created_at are insert-only: excluded from the update column list.
            CityboxDevice::upsert($rows, ['equipment_id'], [
                'name', 'type', 'ops_status', 'ops_status_label', 'online',
                'heartbeat_last_recovery', 'heartbeat_last_offline',
                'client_name', 'location', 'address', 'in_fleet', 'last_seen_at', 'updated_at',
            ]);
        }

        // A complete listing is the whole fleet: anything not in it has left.
        // An EMPTY complete listing is not believed: while chillers are linked
        // an empty fleet is far likelier to be a transient bad response than a
        // real mass removal, and acting on it would blank the Create picker and
        // every find() until the next sweep. A filtered listing says nothing
        // about the rest.
        if ($complete && $keyed->isNotEmpty()) {
            CityboxDevice::where('in_fleet', true)
                ->whereNotIn('equipment_id', $keyed->keys()->all())
                ->update(['in_fleet' => false, 'updated_at' => now()]);
        }

        return $keyed;
    }

    /** Call their API and persist. The one place the fleet is pulled outside the poller. */
    public function refresh(array $filters = []): Collection
    {
        return $this->record($this->gateway->listDevices($filters), complete: $filters === []);
    }

    /** Fleet from the table, refreshing first if it is empty or stale (or $fresh). */
    public function fleet(bool $fresh = false): Collection
    {
        if ($fresh || $this->isStale()) {
            $this->refresh();
        }

        return CityboxDevice::toDtos(CityboxDevice::inFleet()->orderBy('name')->orderBy('equipment_id')->get())
            ->keyBy(fn (ChillerDevice $d) => $d->equipmentId);
    }

    /** Devices no vend has adopted, table-served. Same staleness rule. */
    public function unlinked(bool $fresh = false): Collection
    {
        if ($fresh || $this->isStale()) {
            $this->refresh();
        }

        // inFleet: a device that has dropped out of box_list keeps its row
        // (history) but must not be offered for provisioning.
        return CityboxDevice::toDtos(CityboxDevice::unlinked()->inFleet()->orderBy('name')->orderBy('equipment_id')->get());
    }

    public function find(string $equipmentId, bool $fresh = false): ?ChillerDevice
    {
        if ($fresh || $this->isStale()) {
            $this->refresh();
        }

        return CityboxDevice::where('equipment_id', $equipmentId)->inFleet()->first()?->toDto();
    }

    public function isStale(): bool
    {
        $newest = CityboxDevice::max('last_seen_at');

        return $newest === null || now()->subMinutes(self::FRESH_MINUTES)->gt($newest);
    }
}
