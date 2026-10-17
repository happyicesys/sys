<?php

namespace App\Support;

use App\Models\Vend;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * A payment rail fitted to a machine that has taken no sale for a while (Brian, 2026-10-17:
 * "machine setting and components, sometime long time in no function condition"). The Operation
 * Dashboard draws a red frame on the Card Terminal, LCD Monitor (QR / Omise) and Coin Float
 * badges from this, so a dead reader, a QR that no longer shows or a jammed coin mech is seen
 * before days of lost sales.
 *
 * Reads only `vends` columns the TRADE ingest keeps (VendTransactionService::updateVendPaymentTimestamps:
 * cash = payment method code 0, card = code 1, cashless = a payment-gateway method), so it costs no
 * query. A rail counts only when the machine HAS it: a card terminal set, an LCD monitor fitted (the
 * QR shows on it), a coin float reported. Active machines only — a testing or inactive one is quiet
 * on purpose.
 */
class PaymentRailIdle
{
    public const CARD = 'card';

    public const QR = 'qr';

    public const CASH = 'cash';

    private const COLUMNS = [
        self::CARD => 'last_card_vend_transaction_at',
        self::QR => 'last_cashless_vend_transaction_at',
        self::CASH => 'last_cash_vend_transaction_at',
    ];

    public static function hours(): int
    {
        return max(1, (int) config('vend.attention.payment_idle_hours', 48));
    }

    /**
     * @param  array<string, mixed>  $row  a grid row's attributes (vends columns as selected by the Operation Dashboard)
     * @return array<string, array{last_at: ?string, hours_since: ?float, machine_idle: bool}> idle rails only
     */
    public static function forRow(array $row, ?CarbonInterface $now = null): array
    {
        $isActive = (bool) ($row['vend_is_active'] ?? $row['is_active'] ?? false);
        if (! $isActive || (bool) ($row['is_testing'] ?? false)) {
            return [];
        }

        $now = $now ? Carbon::instance($now) : Carbon::now();
        $cutoff = $now->copy()->subHours(self::hours());
        $lastAny = self::time($row['last_vend_transaction_at'] ?? null);
        $machineIdle = $lastAny === null || $lastAny->lt($cutoff);

        $idle = [];
        foreach (self::COLUMNS as $rail => $column) {
            if (! self::fitted($rail, $row)) {
                continue;
            }
            $last = self::time($row[$column] ?? null);
            if ($last !== null && $last->gte($cutoff)) {
                continue;
            }
            $idle[$rail] = [
                'last_at' => $last?->toIso8601String(),
                'hours_since' => $last ? round($last->diffInMinutes($now) / 60, 1) : null,
                'machine_idle' => $machineIdle,
            ];
        }

        return $idle;
    }

    private static function fitted(string $rail, array $row): bool
    {
        return match ($rail) {
            self::CARD => ! empty($row['card_terminal_id']),
            self::QR => ! empty($row['lcd_monitor_id'])
                && (Vend::LCD_MONITOR_SHORT_MAPPINGS[$row['lcd_monitor_id']] ?? 'N/A') !== 'N/A',
            self::CASH => ! empty(self::parameters($row)['CoinCnt']),
        };
    }

    private static function parameters(array $row): array
    {
        $json = $row['parameter_json'] ?? null;
        if (is_string($json)) {
            $json = json_decode($json, true);
        }

        return is_array($json) ? $json : [];
    }

    private static function time(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof CarbonInterface ? Carbon::instance($value) : Carbon::parse($value);
    }
}
