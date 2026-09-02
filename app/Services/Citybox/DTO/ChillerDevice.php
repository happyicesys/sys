<?php

namespace App\Services\Citybox\DTO;

use App\Enums\Citybox\DeviceOpsStatus;
use App\Enums\Citybox\DeviceType;
use Carbon\CarbonImmutable;

/**
 * One row of `box_list`. Immutable fact about a device at poll time.
 * All parsing of their (type-drifting) fields happens HERE and nowhere else.
 */
final readonly class ChillerDevice
{
    public function __construct(
        public string $equipmentId,
        public string $name,
        public DeviceType $type,
        public ?DeviceOpsStatus $opsStatus,
        public bool $online,
        public ?CarbonImmutable $heartbeatRecovery,
        public ?CarbonImmutable $heartbeatOffline,
        public ?string $opsStatusLabel,
        public ?string $onlineLabel,
        // Not on box_list as of 2026-09-02 (requested from CityBox); parsed when present.
        public ?string $clientName = null,
        public ?string $location = null,
        public ?string $address = null,
    ) {}

    public static function fromApi(array $row): self
    {
        return new self(
            equipmentId: (string) ($row['equipment_id'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            type: DeviceType::fromApi($row['type'] ?? null),
            opsStatus: DeviceOpsStatus::fromApi($row['status'] ?? null),
            online: (int) ($row['equipment_online_status'] ?? 0) === 1,
            heartbeatRecovery: self::ts($row['heartbeat_last_recovery'] ?? null),
            heartbeatOffline: self::ts($row['heartbeat_last_offline'] ?? null),
            opsStatusLabel: isset($row['equipment_status_str']) ? (string) $row['equipment_status_str'] : null,
            onlineLabel: isset($row['equipment_online_status_str']) ? (string) $row['equipment_online_status_str'] : null,
            clientName: self::str($row['client_name'] ?? $row['client'] ?? null),
            location: self::str($row['location'] ?? null),
            address: self::str($row['address'] ?? null),
        );
    }

    /** Registry row shape (CityboxDevice upsert). Timestamps are the caller's. */
    public function toRegistryRow(): array
    {
        return [
            'equipment_id' => $this->equipmentId,
            'name' => $this->name,
            'type' => $this->type->value,
            'ops_status' => $this->opsStatus?->value,
            'ops_status_label' => $this->opsStatusLabel,
            'online' => $this->online,
            'heartbeat_last_recovery' => $this->heartbeatRecovery?->toDateTimeString(),
            'heartbeat_last_offline' => $this->heartbeatOffline?->toDateTimeString(),
            'client_name' => $this->clientName,
            'location' => $this->location,
            'address' => $this->address,
        ];
    }

    private static function str(mixed $v): ?string
    {
        $v = is_scalar($v) ? trim((string) $v) : '';

        return $v === '' ? null : $v;
    }

    private static function ts(mixed $v): ?CarbonImmutable
    {
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
