<?php

namespace App\Services\Citybox;

use App\Enums\Citybox\DeviceOpsStatus;
use App\Enums\Citybox\DeviceType;
use App\Models\Vend;
use Carbon\CarbonImmutable;

/**
 * The CityBox-side status of one Smart Chiller, as a read-only value object.
 *
 * This is the "logical layer" between what their API says about a device and
 * what mark1 shows: it is built from the last poll result already stored on
 * the vend (`citybox_status_json`, `is_online`, `citybox_synced_at`) — never
 * from a live API call — so rendering it costs nothing.
 *
 * Deliberately NOT a mirror of mark1's own Status field. mark1's Status is a
 * projection over is_active / is_testing / is_disposed / is_sold, three of
 * which describe OUR asset register and have no counterpart in their API.
 * This object describes THEIR view (running / banned / being removed /
 * removed, online / offline, heartbeats). The two sit side by side on the
 * settings page; neither writes the other. `isRetired()` is exposed so a
 * later rule ("已撤机 ⇒ inactive") has a single predicate to key on.
 */
final readonly class ChillerStatus
{
    /** Polls run every minute; older than this and the row is not "live". */
    public const STALE_AFTER_MINUTES = 5;

    public function __construct(
        public ?string $equipmentId,
        public ?string $name,
        public DeviceType $deviceType,
        public ?DeviceOpsStatus $opsStatus,
        public ?string $opsStatusLabel,
        public bool $online,
        public ?CarbonImmutable $heartbeatRecovery,
        public ?CarbonImmutable $heartbeatOffline,
        public ?CarbonImmutable $syncedAt,
    ) {}

    public static function forVend(Vend $vend): self
    {
        $json = is_array($vend->citybox_status_json) ? $vend->citybox_status_json : [];

        return new self(
            equipmentId: $vend->citybox_equipment_id ?: null,
            name: isset($json['name']) ? (string) $json['name'] : null,
            deviceType: DeviceType::fromApi($json['device_type'] ?? null),
            opsStatus: DeviceOpsStatus::fromApi($json['equipment_status'] ?? null),
            opsStatusLabel: isset($json['equipment_status_str']) ? (string) $json['equipment_status_str'] : null,
            online: (bool) $vend->is_online,
            heartbeatRecovery: self::ts($json['heartbeat_last_recovery'] ?? null),
            heartbeatOffline: self::ts($json['heartbeat_last_offline'] ?? null),
            syncedAt: self::ts($vend->citybox_synced_at),
        );
    }

    /** Linked to a device and polled at least once. */
    public function isKnown(): bool
    {
        return $this->equipmentId !== null && $this->syncedAt !== null;
    }

    /** Their ops status says the machine can trade (启运). */
    public function isRunning(): bool
    {
        return $this->opsStatus?->isRunning() ?? false;
    }

    /** 已撤机 — CityBox has retired the unit. The hook for a future auto-deactivate rule. */
    public function isRetired(): bool
    {
        return $this->opsStatus === DeviceOpsStatus::Removed;
    }

    /** No poll result within STALE_AFTER_MINUTES — "online" cannot be trusted. */
    public function isStale(?CarbonImmutable $now = null): bool
    {
        if ($this->syncedAt === null) {
            return true;
        }

        return $this->syncedAt->lt(($now ?? CarbonImmutable::now())->subMinutes(self::STALE_AFTER_MINUTES));
    }

    /** Human label for their ops status: our enum's label, else their raw string, else "Unknown". */
    public function opsLabel(): string
    {
        return $this->opsStatus?->label() ?? ($this->opsStatusLabel ?: 'Unknown');
    }

    /** One line for lists and toasts: "Running (启运) · online · synced 2 min ago". */
    public function summary(): string
    {
        if (! $this->isKnown()) {
            return $this->equipmentId ? 'Linked, not yet polled' : 'Not linked to a CityBox device';
        }

        $parts = [$this->opsLabel(), $this->online ? 'online' : 'offline'];
        if ($this->isStale()) {
            $parts[] = 'STALE — last sync '.$this->syncedAt->diffForHumans();
        } else {
            $parts[] = 'synced '.$this->syncedAt->diffForHumans();
        }

        return implode(' · ', $parts);
    }

    /** Inertia/JSON shape for the settings page. */
    public function toArray(): array
    {
        return [
            'equipment_id' => $this->equipmentId,
            'name' => $this->name,
            'device_type' => $this->deviceType->value,
            'model' => $this->deviceType->modelName(),
            'ops_status' => $this->opsStatus?->value,
            'ops_label' => $this->opsLabel(),
            'is_running' => $this->isRunning(),
            'is_retired' => $this->isRetired(),
            'online' => $this->online,
            'heartbeat_last_recovery' => $this->heartbeatRecovery?->toDateTimeString(),
            'heartbeat_last_offline' => $this->heartbeatOffline?->toDateTimeString(),
            'synced_at' => $this->syncedAt?->toDateTimeString(),
            'is_stale' => $this->isStale(),
            'is_known' => $this->isKnown(),
            'summary' => $this->summary(),
        ];
    }

    private static function ts(mixed $v): ?CarbonImmutable
    {
        if ($v instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($v);
        }
        if (! is_string($v) || $v === '' || str_starts_with($v, '0000-')) {
            return null;
        }
        try {
            return CarbonImmutable::parse($v);
        } catch (\Throwable) {
            return null;
        }
    }
}
