<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Collection;

/**
 * Serializes QtyTier campaigns onto the legacy bundle-tier wire keys that
 * every deployed APK already executes (enableBundleDiscount, bundleStartDate,
 * bundleEndDate, enableDiscount01..03, discountPercent01..03).
 *
 * On-device the tiers are cart-quantity thresholds evaluated highest-first:
 * >=2 items → discountPercent01, >=3 → 02, >=4 → 03, gated by
 * checkWithinDate(bundleStartDate, bundleEndDate). Wire types are sacred —
 * enables are the STRINGS 'true'/'false', percents are real ints, dates are
 * 'Y-m-d' strings or null (see App\ValueObjects\ApkSettingParameters).
 */
class CampaignWireSerializer
{
    /**
     * Tier threshold (bundle_qty) → legacy wire slot suffix.
     */
    private const SLOT_BY_QTY = [
        2 => '01',
        3 => '02',
        4 => '03',
    ];

    /**
     * Override the seven legacy tier keys from the bound active QtyTier
     * campaigns.
     *
     * Backward-compatibility contract: when the collection contains NO
     * QtyTier campaign, $settingsParams is returned completely untouched —
     * profiles using the legacy hand-set fields must produce byte-identical
     * output.
     *
     * Duplicate thresholds resolve deterministically: campaigns are applied
     * in ascending campaign id order, so the highest id wins its slot
     * (last write wins).
     *
     * @param  array  $settingsParams  the normalized settings blob
     * @param  iterable  $activeCampaigns  the profile's active campaign bindings
     */
    public function applyQtyTierOverrides(array $settingsParams, $activeCampaigns): array
    {
        $tiers = $this->qtyTiersOf($activeCampaigns)->sortBy('id')->values();

        if ($tiers->isEmpty()) {
            return $settingsParams;
        }

        // Unfilled slots are explicitly disabled — a stale hand-set tier must
        // not leak through beside campaign-authored ones.
        foreach (self::SLOT_BY_QTY as $slot) {
            $settingsParams['enableDiscount'.$slot] = 'false';
            $settingsParams['discountPercent'.$slot] = 1;
        }

        foreach ($tiers as $campaign) {
            $slot = self::SLOT_BY_QTY[(int) $campaign->bundle_qty] ?? null;

            if ($slot === null) {
                continue; // out-of-range threshold — validation should prevent this
            }

            $settingsParams['enableDiscount'.$slot] = 'true';
            $settingsParams['discountPercent'.$slot] = (int) $campaign->value;
        }

        $settingsParams['enableBundleDiscount'] = 'true';

        // Window = union of the tiers' windows, and the device applies ONE
        // window to the whole ladder (checkWithinDate treats an empty bound
        // as unbounded). So on BOTH bounds, ANY tier with a null date makes
        // that bound unbounded — otherwise an undated (always-active) tier
        // would be wrongly suppressed outside a dated sibling's window.
        $starts = $tiers->pluck('start_at');
        $settingsParams['bundleStartDate'] = $starts->contains(null)
            ? null
            : $starts->min()->format('Y-m-d');

        $ends = $tiers->pluck('end_at');
        $settingsParams['bundleEndDate'] = $ends->contains(null)
            ? null
            : $ends->max()->format('Y-m-d');

        return $settingsParams;
    }

    /**
     * Campaigns safe to ship in the wire campaigns[] array. QtyTier campaigns
     * are excluded: deployed APK label engines don't know the type — the
     * derived flat tier keys are the delivery mechanism for ALL versions.
     */
    public function excludeQtyTier($campaigns): Collection
    {
        return collect($campaigns)
            ->reject(fn ($campaign) => ($campaign->promo_type ?? null) === Campaign::TYPE_QTY_TIER)
            ->values();
    }

    private function qtyTiersOf($campaigns): Collection
    {
        return collect($campaigns)
            ->filter(fn ($campaign) => ($campaign->promo_type ?? null) === Campaign::TYPE_QTY_TIER);
    }
}
