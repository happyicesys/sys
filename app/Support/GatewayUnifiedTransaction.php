<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Single source of truth for whether the "unified transactions" feature
 * (create the vend_transaction at gateway paid-time, instead of waiting for the
 * machine's TRADE) is active. Used by the paid-webhook dispatch and the refund
 * settlement writes so they can never disagree.
 *
 * Activation is driven entirely by config/.env — no code change to flip:
 *   GATEWAY_UNIFIED_TXN_ENABLED=true         → force on immediately
 *   GATEWAY_UNIFIED_TXN_START_AT="2026-05-26 00:00:00"
 *                                            → auto-on once that moment passes
 *   neither set / date in the future         → off
 *
 * The date comparison runs at call time (not at config-cache time), so once the
 * .env value is deployed the feature switches itself on at the configured
 * instant with no manual step and no config:clear needed at the cutover.
 * The datetime is interpreted in the app timezone (per-country deploy: app TZ =
 * operator TZ), so "00:00:00" means local midnight.
 */
class GatewayUnifiedTransaction
{
    /**
     * Master/time gate, independent of the per-machine pilot allowlist.
     */
    public static function isEnabled(): bool
    {
        // Explicit override wins.
        if (filter_var(config('app.gateway_unified_txn_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        // Scheduled activation: on once "now" reaches the configured instant.
        $startAt = config('app.gateway_unified_txn_start_at');
        if (! empty($startAt)) {
            try {
                return Carbon::now()->greaterThanOrEqualTo(Carbon::parse($startAt));
            } catch (\Throwable $e) {
                // A malformed date must never silently enable the feature.
                return false;
            }
        }

        return false;
    }

    /**
     * Whether the feature applies to a specific machine: the gate above AND the
     * optional pilot allowlist. Empty allowlist = all machines once enabled.
     */
    public static function appliesToVend(?string $vendCode): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        $allow = trim((string) config('app.gateway_unified_txn_vend_codes', ''));
        if ($allow === '') {
            return true;
        }

        $codes = array_filter(array_map('trim', explode(',', $allow)));

        return in_array((string) $vendCode, $codes, true);
    }
}
