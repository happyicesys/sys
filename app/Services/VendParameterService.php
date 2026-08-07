<?php

namespace App\Services;

use App\ValueObjects\ApkSettingParameters;

/**
 * Thin facade kept for the existing call sites (ApkSettingController,
 * SettingController::updateParameter). The schema itself — keys, defaults,
 * legacy null rules, canonical order — now lives in ONE place:
 * App\ValueObjects\ApkSettingParameters. Add new settings there, not here.
 */
class VendParameterService
{
    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameter()
    {
        return ApkSettingParameters::defaults()->toArray();
    }

    /**
     * Normalize an arbitrary input array (request payload, legacy row) into
     * the full canonical parameter set. Unlike the old hand-written mapping,
     * a missing key no longer throws "Undefined array key" — it falls back
     * to the schema default.
     *
     * @param array<string, mixed>|null $parameters
     * @return array<string, mixed>
     */
    public function getCampaignParameter($parameters)
    {
        return ApkSettingParameters::fromArray($parameters)->toArray();
    }

    /**
     * Normalize an UPDATE payload against the row it is updating.
     *
     * Use this, not getCampaignParameter(), whenever an existing record is
     * being saved. A key absent from the payload keeps its CURRENT stored
     * value instead of snapping back to the schema default.
     *
     * Why it matters: vends.settings_parameter_json carries 30 of the 42
     * canonical keys on all 1115 live rows - 16 are simply not there, and no
     * form posts them. Normalizing against DEFAULTS alone therefore rewrites
     * them on every save, including selectedPricingSource, which would flip a
     * server-priced machine to 'machine' the moment somebody edited a banner
     * and then publish TYPESYNCSETTINGSPARAM to it. (Before the value object
     * this path threw "Undefined array key" and wrote nothing, which is why
     * the hazard is new rather than long-standing.)
     *
     * Defaults still apply to any key present in NEITHER the payload nor the
     * stored row, so a genuinely new setting self-heals exactly as before.
     *
     * @param array<string, mixed>|null $existing  the row's current value
     * @param array<string, mixed>|null $parameters  the incoming payload
     * @return array<string, mixed>
     */
    public function mergeCampaignParameter($existing, $parameters)
    {
        return ApkSettingParameters::fromArray(array_merge(
            is_array($existing) ? $existing : [],
            is_array($parameters) ? $parameters : []
        ))->toArray();
    }
}
