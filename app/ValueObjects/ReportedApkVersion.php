<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * The APK versionCode a machine last reported, and which channel reported it.
 *
 * Two channels write it, and no machine uses both:
 *   - FRAME: the vending boards (mark1-apk / mark1-apk-small) send PWRON over
 *     MQTT, which lands in `vends.apk_ver_json` as {apkver, buildtime,
 *     deviceType}. It carries a build timestamp.
 *   - OTA: every APK that talks to /ota/manifest — the smart freezer
 *     (sg.mark1.freezer) only ever reports here — stamps
 *     `vends.apk_version_code` + `vends.apk_checked_in_at`. No build time.
 *
 * So a smart freezer has apk_ver_json NULL forever, and reading only that
 * column (as the Operation Dashboard did until 2026-09-16) shows its version
 * as blank even though the machine checks in daily.
 *
 * The rule is `max()`, not "prefer one column": a board that OTA'd after its
 * last PWRON has the fresher number in apk_version_code, and vice versa. 0
 * means never reported by either channel.
 *
 * Build it from ANY row shape — an Eloquent model with the json cast applied,
 * a joined query-builder row with a raw JSON string, or a stdClass — so the
 * one rule serves Vend::reportedApkVersion() and VendResource alike. Callers
 * must never re-derive it; three of them had grown partial variants before.
 */
final class ReportedApkVersion implements JsonSerializable
{
    /** Reported by the OTA check-in (vends.apk_version_code). */
    public const SOURCE_OTA = 'ota';

    /** Reported by the PWRON MQTT frame (vends.apk_ver_json). */
    public const SOURCE_FRAME = 'frame';

    private function __construct(
        public readonly int $code,
        public readonly ?string $source,
        public readonly ?string $buildTime,
        public readonly mixed $checkedInAt,
        public readonly ?string $deviceType,
    ) {}

    /**
     * @param  mixed  $versionCode  vends.apk_version_code (int, numeric string or null)
     * @param  mixed  $apkVerJson  vends.apk_ver_json (array, object, JSON string or null)
     * @param  mixed  $checkedInAt  vends.apk_checked_in_at, passed through untouched
     */
    public static function fromAttributes(mixed $versionCode, mixed $apkVerJson, mixed $checkedInAt = null): self
    {
        $frame = self::decode($apkVerJson);

        $otaCode = is_numeric($versionCode) ? (int) $versionCode : 0;
        $frameCode = isset($frame['apkver']) && is_numeric($frame['apkver']) ? (int) $frame['apkver'] : 0;

        // Ties go to the frame: same number, but it carries the build time.
        $source = match (true) {
            $frameCode > 0 && $frameCode >= $otaCode => self::SOURCE_FRAME,
            $otaCode > 0 => self::SOURCE_OTA,
            default => null,
        };

        return new self(
            code: max($otaCode, $frameCode),
            source: $source,
            buildTime: $source === self::SOURCE_FRAME && isset($frame['buildtime'])
                ? (string) $frame['buildtime']
                : null,
            checkedInAt: $checkedInAt,
            deviceType: isset($frame['deviceType']) ? (string) $frame['deviceType'] : null,
        );
    }

    /** Convenience for any row carrying the three columns (model or stdClass). */
    public static function fromRow(object $row): self
    {
        return self::fromAttributes(
            $row->apk_version_code ?? null,
            $row->apk_ver_json ?? null,
            $row->apk_checked_in_at ?? null,
        );
    }

    public function isReported(): bool
    {
        return $this->code > 0;
    }

    /** True while the machine has only ever reported through /ota/manifest. */
    public function isOtaOnly(): bool
    {
        return $this->source === self::SOURCE_OTA;
    }

    /**
     * Narrow a query to the machines whose REPORTED version starts with
     * `$search` — the "APK Ver" box on the Operation Dashboard and on
     * Vend/Index. Both channels are matched, or a smart freezer (OTA only)
     * would be invisible to the same filter that renders its version.
     *
     * Prefix semantics on both columns, so "30" still finds the 30x stream as
     * it always did, and "1" finds both v11 and v134. Neither column is
     * indexed (checked on prod 2026-09-16) and the OR would preclude an index
     * anyway, so casting the int column for the LIKE costs nothing and keeps
     * one rule for the user instead of "exact here, prefix there".
     *
     * Call this instead of touching either column — the two-channel rule
     * lives in this class alone.
     *
     * @template TQuery of \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     *
     * @param  TQuery  $query
     * @param  string  $table  qualifier, so a joined query stays unambiguous
     * @return TQuery
     */
    public static function applyVersionFilter($query, mixed $search, string $table = 'vends')
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        // $table reaches raw SQL below; only ever a table name from our own
        // call sites, but never let anything else through.
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new InvalidArgumentException("Invalid table qualifier [{$table}].");
        }

        return $query->where(function ($query) use ($search, $table) {
            $query->where($table.'.apk_ver_json->apkver', 'LIKE', $search.'%')
                ->orWhereRaw('CAST('.$table.'.apk_version_code AS CHAR) LIKE ?', [$search.'%']);
        });
    }

    /**
     * Normalize whatever the column handed us into an array. A query-builder
     * row gives the raw JSON string; an Eloquent model with the cast gives an
     * array; json_decode gives stdClass when a caller decoded it already.
     *
     * @return array<string, mixed>
     */
    private static function decode(mixed $apkVerJson): array
    {
        if (is_array($apkVerJson)) {
            return $apkVerJson;
        }

        if (is_object($apkVerJson)) {
            return (array) $apkVerJson;
        }

        if (is_string($apkVerJson) && $apkVerJson !== '') {
            $decoded = json_decode($apkVerJson, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Wire shape for the Inertia pages. `checked_in_at` stays raw here —
     * VendResource formats it in the user's timezone, and only for the OTA
     * case, so the dashboard does not parse a Carbon per row for the whole
     * vending fleet.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'source' => $this->source,
            'build_time' => $this->buildTime,
            'checked_in_at' => $this->checkedInAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
