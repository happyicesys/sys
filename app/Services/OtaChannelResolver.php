<?php

namespace App\Services;

use App\Models\Vend;
use App\Models\VendModel;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * OtaChannelResolver — single source of truth for "which APK does this machine get?".
 *
 * mark1 serves more than one Android build (legacy vending APK, smart-freezer APK).
 * Every question that depends on that split — which releases to list, which fleet to
 * count, which machines to nudge, which manifest to serve — routes through here so
 * the mapping lives in config/ota.php and not scattered across controllers.
 *
 * Resolution order for a polling device (most trustworthy first):
 *   1. the applicationId the device reports (?package=) — cannot be stale
 *   2. the vend's vend_model row — hand-maintained, so only a fallback
 *   3. the configured default channel
 *
 * @see config/ota.php
 */
class OtaChannelResolver
{
    /** All channels, keyed by channel key. */
    public function all(): array
    {
        return (array) config('ota.channels', []);
    }

    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function exists(?string $channel): bool
    {
        return $channel !== null && array_key_exists($channel, $this->all());
    }

    public function default(): string
    {
        $default = (string) config('ota.default_channel');

        return $this->exists($default) ? $default : (string) array_key_first($this->all());
    }

    /**
     * Config block for one channel.
     *
     * @throws InvalidArgumentException on an unknown key — callers must validate
     *         user input against exists() first.
     */
    public function config(string $channel): array
    {
        if (! $this->exists($channel)) {
            throw new InvalidArgumentException("Unknown OTA channel [{$channel}].");
        }

        return $this->all()[$channel];
    }

    public function label(string $channel): string
    {
        return (string) ($this->config($channel)['label'] ?? $channel);
    }

    public function packageName(string $channel): ?string
    {
        return $this->config($channel)['package_name'] ?? null;
    }

    public function storageFolder(string $channel): string
    {
        return trim((string) ($this->config($channel)['storage_folder'] ?? 'sys/vends/apk'), '/');
    }

    /**
     * Normalise a caller-supplied channel to a known key, falling back to the
     * default. Use for query-string / request input.
     */
    public function normalise(?string $channel): string
    {
        return $this->exists($channel) ? $channel : $this->default();
    }

    /** Channel whose package_name matches, or null. Case-insensitive, trimmed. */
    public function forPackage(?string $package): ?string
    {
        $package = trim((string) $package);

        if ($package === '') {
            return null;
        }

        foreach ($this->all() as $key => $cfg) {
            if (strcasecmp((string) ($cfg['package_name'] ?? ''), $package) === 0) {
                return $key;
            }
        }

        return null;
    }

    /** Channel implied by a vend's model, or null when the vend is unknown. */
    public function forVend(?Vend $vend): ?string
    {
        if (! $vend) {
            return null;
        }

        $modelName = $vend->relationLoaded('vendModel')
            ? $vend->vendModel?->name
            : VendModel::query()->whereKey($vend->vend_model_id)->value('name');

        if ($modelName === null) {
            return null;
        }

        foreach ($this->all() as $key => $cfg) {
            if (($cfg['vend_model'] ?? null) !== null && $cfg['vend_model'] === $modelName) {
                return $key;
            }
        }

        // A model nobody claims belongs to the catch-all channel (vend_model => null).
        return $this->catchAllChannel();
    }

    /**
     * The channel that owns every vend model no other channel claims, or null when
     * every channel is explicitly scoped.
     *
     * A channel scoped by device_types (e.g. vending_small) also has vend_model null
     * — because no vend model can identify its fleet — but it is explicitly scoped,
     * not a catch-all, so it is skipped here regardless of config order.
     */
    public function catchAllChannel(): ?string
    {
        foreach ($this->all() as $key => $cfg) {
            // array_filter, matching scopeFleet() and forDeviceType(): a typo'd
            // ['device_types' => ['']] must not read as "scoped" here while
            // reading as "unscoped" there.
            $types = array_values(array_filter((array) ($cfg['device_types'] ?? [])));

            if (($cfg['vend_model'] ?? null) === null && $types === []) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Channel that claims this board family (apk_ver_json.deviceType), or null.
     *
     * Uses the same array_filter form as scopeFleet() so a typo'd config like
     * ['device_types' => ['']] is treated identically by both.
     */
    public function forDeviceType(?string $deviceType): ?string
    {
        if ($deviceType === null || $deviceType === '') {
            return null;
        }

        foreach ($this->all() as $key => $cfg) {
            $types = array_values(array_filter((array) ($cfg['device_types'] ?? [])));

            if ($types !== [] && in_array($deviceType, $types, true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Full resolution for a polling device: reported package first, then the vend's
     * model, then the configured default — with a board-family BACKSTOP.
     *
     * The backstop exists because the vending / vending_small split rests entirely
     * on one string constant inside the APK (OtaCoordinator.OTA_CHANNEL_PACKAGE).
     * Both builds ship the real applicationId "com.venderroute", so if a no-touch
     * board polls WITHOUT that constant — an older build, a build predating the
     * constant, a hand-sideloaded one — package routing cannot see it, forVend()
     * finds no vend_model claim, and it lands on the catch-all and is offered the
     * TOUCHSCREEN build. Same applicationId and same signer means every on-device
     * gate passes and it installs in place.
     *
     * Live as of 2026-08-06: 222 active ZC-83A boards, `vending` publishes v308 at
     * rollout 1000, `vending_small` has no release yet. None of those 222 has ever
     * checked in, so this is latent rather than live — it arms the day the first
     * small build carrying an OTA client is installed.
     *
     * Deliberately narrow, and it can only ever WITHHOLD a build, never offer a new
     * one: it applies solely when the device's own reported board family is claimed
     * by a channel, and only redirects AWAY from a channel that does not claim it.
     * A device reporting the small package still routes by package exactly as
     * before; a board family no channel claims (ZC-328, INPAD3101, or a vend with
     * no apk_ver_json) is untouched.
     */
    public function resolve(?string $package, ?Vend $vend = null): string
    {
        $channel = $this->forPackage($package)
            ?? $this->forVend($vend)
            ?? $this->default();

        $claimedByBoard = $this->forDeviceType(
            $vend?->apk_ver_json['deviceType'] ?? null
        );

        if ($claimedByBoard !== null && $claimedByBoard !== $channel) {
            return $claimedByBoard;
        }

        return $channel;
    }

    /**
     * Vend model names claimed by a specific channel (i.e. all channels except the
     * catch-all). Used to build the catch-all's "everything else" query.
     */
    public function claimedVendModels(): array
    {
        return array_values(array_filter(array_map(
            fn ($cfg) => $cfg['vend_model'] ?? null,
            $this->all()
        )));
    }

    /**
     * Constrain a Vend query to the fleet belonging to one channel.
     *
     * A device_types channel matches on the board family the APK itself reports
     * (apk_ver_json.deviceType, from PWRON telemetry) — used where vend models span
     * more than one fleet. An explicitly-scoped channel matches its vend model by
     * name; the catch-all channel matches everything the others do not claim by
     * VEND MODEL (including vends with no model row at all). Deliberately, the
     * catch-all does NOT subtract device_types fleets: those machines stay visible
     * on the existing vending tab exactly as before — the device_types channel is
     * an additional, sharper view, and push-OTA-check overlap is harmless because a
     * device only ever polls (and is served from) its own package-routed channel.
     */
    public function scopeFleet(Builder $query, string $channel): Builder
    {
        $cfg = $this->config($channel);

        $deviceTypes = array_values(array_filter((array) ($cfg['device_types'] ?? [])));

        if ($deviceTypes !== []) {
            return $query->whereIn('apk_ver_json->deviceType', $deviceTypes);
        }

        $modelName = $cfg['vend_model'] ?? null;

        if ($modelName !== null) {
            return $query->whereHas('vendModel', fn ($q) => $q->where('name', $modelName));
        }

        $claimed = $this->claimedVendModels();

        if (empty($claimed)) {
            return $query;
        }

        return $query->whereDoesntHave('vendModel', fn ($q) => $q->whereIn('name', $claimed));
    }
}
