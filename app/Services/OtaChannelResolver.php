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
     */
    public function catchAllChannel(): ?string
    {
        foreach ($this->all() as $key => $cfg) {
            if (($cfg['vend_model'] ?? null) === null) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Full resolution for a polling device: reported package first, then the vend's
     * model, then the configured default.
     */
    public function resolve(?string $package, ?Vend $vend = null): string
    {
        return $this->forPackage($package)
            ?? $this->forVend($vend)
            ?? $this->default();
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
     * An explicitly-scoped channel matches its vend model by name; the catch-all
     * channel matches everything the others do not claim (including vends with no
     * model row at all).
     */
    public function scopeFleet(Builder $query, string $channel): Builder
    {
        $modelName = $this->config($channel)['vend_model'] ?? null;

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
