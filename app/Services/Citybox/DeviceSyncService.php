<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Exceptions\CityboxApiException;
use App\Models\Vend;
use App\Services\Citybox\DTO\ChillerDevice;
use App\Support\VendCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fleet STATUS half of the poll: box_list → linked Smart Chiller vends
 * (is_online, heartbeats, ops status, type, CityBox name, machine ID). One responsibility;
 * stock lives in StockPollService.
 *
 * Rules (unchanged from phase 2):
 *  - Never creates vends: unknown equipment_ids are reported, not imported.
 *  - Never flips is_active on their 禁运/撤机 status — ops decision; a
 *    TRANSITION into non-running is logged once, not every poll.
 */
class DeviceSyncService
{
    public function __construct(private ChillerGateway $gateway, private CityboxDeviceRegistry $registry) {}

    /** Scheduler guard: any Smart Chiller linked at all? Indexed EXISTS. */
    public static function hasLinkedVends(): bool
    {
        return self::linkedVendsQuery()->exists();
    }

    /**
     * Sync fleet status. Returns the fleet map so StockPollService can reuse
     * it without a second box_list call, plus the anomaly sets.
     *
     * @return array{devices: Collection<string,ChillerDevice>, vends: Collection<string,Vend>, matched:int, missing:array, unknown:array}
     */
    public function syncFleet(): array
    {
        $vends = self::linkedVendsQuery()->get()->keyBy('citybox_equipment_id');
        if ($vends->isEmpty()) {
            return ['devices' => collect(), 'vends' => $vends, 'matched' => 0, 'missing' => [], 'unknown' => []];
        }

        // One box_list call per sweep; the registry keeps the fleet (linked or
        // not) as rows, so the Create page and reports never need this call.
        $devices = $this->registry->record($this->gateway->listDevices());

        $matched = 0;
        foreach ($devices as $equipmentId => $device) {
            if ($vend = $vends->get($equipmentId)) {
                $this->applyStatus($vend, $device);
                $matched++;
            }
        }

        return [
            'devices' => $devices,
            'vends' => $vends,
            'matched' => $matched,
            'missing' => $vends->keys()->diff($devices->keys())->values()->all(),
            'unknown' => $devices->keys()->diff($vends->keys())->values()->all(),
        ];
    }

    /** One-vend status refresh (used by Pull). Returns the device row for the caller. */
    public function refreshOne(Vend $vend): ChillerDevice
    {
        $equipmentId = (string) $vend->citybox_equipment_id;
        $device = $this->registry->refresh(['equipment_id' => $equipmentId])->get($equipmentId);

        if (! $device) {
            throw new CityboxApiException("Citybox does not know equipment_id {$equipmentId}");
        }

        $this->applyStatus($vend, $device);

        return $device;
    }

    /** Write status fields onto the vend; leaves the `stock` key of the JSON untouched. */
    public function applyStatus(Vend $vend, ChillerDevice $device): void
    {
        $previous = $vend->citybox_status_json ?? [];
        $status = $device->opsStatus;

        $this->followMachineId($vend, $device, $previous['name'] ?? null);

        $vend->forceFill([
            'is_online' => $device->online,
            // The generic 5-min offline sweeper keys on last_updated_at — touch it
            // while their heartbeat says online so it doesn't flip a live chiller.
            'last_updated_at' => $device->online ? now() : $vend->last_updated_at,
            'citybox_synced_at' => now(),
            'citybox_status_json' => array_merge($previous, [
                'source' => 'openapi',
                'equipment_status' => $status?->value,
                'equipment_status_str' => $device->opsStatusLabel,
                'online' => $device->online,
                'device_type' => $device->type->value,
                'name' => $device->name,
                'heartbeat_last_recovery' => $device->heartbeatRecovery?->toDateTimeString(),
                'heartbeat_last_offline' => $device->heartbeatOffline?->toDateTimeString(),
            ]),
        ])->save();

        $previousStatus = $previous['equipment_status'] ?? null;
        if ($status !== null && ! $status->isRunning() && $status->value !== $previousStatus && $vend->is_active) {
            Log::warning('Citybox reports non-running status for active vend', [
                'vend_id' => $vend->id,
                'equipment_id' => $vend->citybox_equipment_id,
                'equipment_status' => $status->value,
                'previous_status' => $previousStatus,
            ]);
        }
    }

    /**
     * OPS Pro owns the machine ID (Brian, 2026-09-19), so a rename there ("C6001" →
     * "C6004") renumbers the vend here on the next poll. Nothing durable keys on
     * vends.code (history and sales hold vend_id), so the change is safe; what is not
     * safe is two vends sharing a label, so a name that carries no ID, or an ID another
     * vend holds, keeps the current one and warns — once per name change, not per poll.
     */
    private function followMachineId(Vend $vend, ChillerDevice $device, ?string $previousName): void
    {
        $machineId = VendCode::fromExternalName($device->name);
        if ($machineId && $machineId->prefix === $vend->code_prefix && $machineId->number === (int) $vend->code) {
            return;
        }
        $nameChanged = $previousName !== $device->name;

        if (! $machineId) {
            if ($nameChanged) {
                Log::warning('Citybox machine name carries no machine ID — keeping the current one', ['vend_id' => $vend->id, 'equipment_id' => $vend->citybox_equipment_id, 'name' => $device->name, 'kept' => $vend->codeLabel()]);
            }

            return;
        }

        $holder = Vend::withoutGlobalScopes()->whereKeyNot($vend->id)
            ->where('code_prefix', $machineId->prefix)->where('code', $machineId->number)
            ->value('id');
        if ($holder) {
            if ($nameChanged) {
                Log::warning('Citybox machine ID already held by another vend — keeping the current one', ['vend_id' => $vend->id, 'equipment_id' => $vend->citybox_equipment_id, 'wanted' => $machineId->toLabel(), 'held_by_vend_id' => $holder, 'kept' => $vend->codeLabel()]);
            }

            return;
        }

        Log::info('Citybox machine ID follows OPS Pro', ['vend_id' => $vend->id, 'equipment_id' => $vend->citybox_equipment_id, 'from' => $vend->codeLabel(), 'to' => $machineId->toLabel()]);
        $vend->forceFill(['code' => $machineId->number, 'code_prefix' => $machineId->prefix]);
    }

    public static function linkedVendsQuery()
    {
        return Vend::where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)
            ->whereNotNull('citybox_equipment_id');
    }
}
