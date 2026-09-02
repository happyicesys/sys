<?php

namespace App\Models;

use App\Enums\Citybox\DeviceOpsStatus;
use App\Enums\Citybox\DeviceType;
use App\Services\Citybox\DTO\ChillerDevice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * One CityBox device as last reported by `box_list`. Registry only: rows are
 * upserted by the poller (see CityboxDeviceRegistry) and never edited or
 * deleted by hand — the API owns them. The vend that adopted a device points
 * at it through vends.citybox_equipment_id.
 */
class CityboxDevice extends Model
{
    protected $fillable = [
        'equipment_id', 'name', 'type', 'ops_status', 'ops_status_label', 'online',
        'heartbeat_last_recovery', 'heartbeat_last_offline',
        'client_name', 'location', 'address', 'in_fleet', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'online' => 'boolean',
        'in_fleet' => 'boolean',
        'ops_status' => 'integer',
        'heartbeat_last_recovery' => 'immutable_datetime',
        'heartbeat_last_offline' => 'immutable_datetime',
        'first_seen_at' => 'immutable_datetime',
        'last_seen_at' => 'immutable_datetime',
    ];

    public function vend(): HasOne
    {
        return $this->hasOne(Vend::class, 'citybox_equipment_id', 'equipment_id');
    }

    /** Devices no mark1 vend has adopted yet — the Create-page picker source. */
    public function scopeUnlinked(Builder $q): Builder
    {
        return $q->whereNotExists(function ($sub) {
            $sub->selectRaw('1')->from('vends')
                ->whereColumn('vends.citybox_equipment_id', 'citybox_devices.equipment_id');
        });
    }

    /** Listed in the most recent complete box_list sweep. */
    public function scopeInFleet(Builder $q): Builder
    {
        return $q->where('in_fleet', true);
    }

    public function opsStatus(): ?DeviceOpsStatus
    {
        return DeviceOpsStatus::fromApi($this->ops_status);
    }

    public function deviceType(): DeviceType
    {
        return DeviceType::fromApi($this->type);
    }

    /** Back to the wire-shaped DTO every Citybox service already speaks. */
    public function toDto(): ChillerDevice
    {
        return new ChillerDevice(
            equipmentId: $this->equipment_id,
            name: (string) $this->name,
            type: $this->deviceType(),
            opsStatus: $this->opsStatus(),
            online: $this->online,
            heartbeatRecovery: $this->heartbeat_last_recovery ? CarbonImmutable::instance($this->heartbeat_last_recovery) : null,
            heartbeatOffline: $this->heartbeat_last_offline ? CarbonImmutable::instance($this->heartbeat_last_offline) : null,
            opsStatusLabel: $this->ops_status_label,
            onlineLabel: null,
            clientName: $this->client_name,
            location: $this->location,
            address: $this->address,
        );
    }

    /** @param  Collection<int,self>  $rows */
    public static function toDtos(Collection $rows): Collection
    {
        return $rows->map(fn (self $d) => $d->toDto());
    }
}
