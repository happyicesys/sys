<?php

namespace App\Casts;

use App\ValueObjects\ApkSettingParameters;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent cast for apk_settings.settings_parameter_json.
 *
 * Read:  decodes the stored JSON and backfills any missing keys with
 *        ApkSettingParameters::DEFAULTS — so rows saved before a new setting
 *        existed are schema-complete everywhere (edit page, resources,
 *        getVendParameters) WITHOUT any DB migration. Nothing is written
 *        back until the row is actually saved by a user action.
 * Write: whitelists to the canonical schema and stores canonical key order.
 *
 * A DB NULL stays NULL in both directions (same as the old 'json' cast), so
 * existing `?? []` / isset() call sites behave identically.
 */
class AsApkSettingParameters implements CastsAttributes
{
    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return null;
        }

        return ApkSettingParameters::fromArray($decoded)->toArray();
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof ApkSettingParameters) {
            $normalized = $value->toArray();
        } else {
            $normalized = ApkSettingParameters::fromArray((array) $value)->toArray();
        }

        return [$key => json_encode($normalized)];
    }
}
