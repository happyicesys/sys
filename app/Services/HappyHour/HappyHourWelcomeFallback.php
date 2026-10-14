<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourCampaign;
use App\Models\Scopes\OperatorHappyHourCampaignScope;
use App\Models\Vend;
use App\Services\VendJobService;
use App\ValueObjects\ApkSettingParameters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The bear welcome scene for a Happy Hour machine that has NO UI Setting (Brian, 2026-10-14:
 * "even it didn't bind with the ui settings, still able to run if there's happy hour binding";
 * 2003 had been unbound from its setting and showed the plain banner).
 *
 * An unbound machine normally gets a 400 from /parameters and keeps whatever it last had. For an
 * eligible big board (app 315+) bound to an ACTIVE Happy Hour campaign, /parameters instead
 * answers with the schema DEFAULTS plus enableWelcomeScene = true: a complete payload, because the
 * APK writes every field of it into its settings (a partial one would reset the rest). It is the
 * same as binding a brand-new UI Setting with the bear on.
 *
 * When such a machine leaves the campaign (removed, campaign off or deleted) its next fetch gets
 * the defaults once more with the bear OFF, then the usual 400 - otherwise the scene would stay on
 * for good. A bound UI Setting always wins; this only ever applies to unbound machines.
 */
final class HappyHourWelcomeFallback
{
    private const SERVED_KEY = 'hh-welcome-fallback:%d';

    /** The /parameters settings for an unbound machine, or null for the usual "not found". */
    public static function parametersFor(Vend $vend): ?array
    {
        $key = sprintf(self::SERVED_KEY, $vend->id);
        if (HappyHourEligibility::supportsVending($vend) && self::inActiveCampaign($vend)) {
            Cache::forever($key, true);

            return self::payload(true);
        }
        if (Cache::pull($key)) {
            return self::payload(false); // the scene goes off once; the next fetch is a 400 again
        }

        return null;
    }

    /**
     * After a campaign is saved or deleted: its machines without a UI Setting re-read /parameters
     * now (TYPESYNCSETTINGSPARAM), so the bear comes on or goes off without an app restart.
     *
     * @param  iterable<int>  $vendIds
     */
    public static function nudgeUnbound(iterable $vendIds): void
    {
        $ids = collect($vendIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return;
        }
        $service = app(VendJobService::class);
        // Unscoped on purpose: a campaign may span operators, and this only tells a machine to
        // re-read /parameters (which decides for itself). Raw NOT EXISTS, not whereDoesntHave(),
        // so no scope inside the subquery can make a bound machine look unbound.
        Vend::query()->withoutGlobalScopes()->whereIn('id', $ids)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('apk_setting_vend')->whereColumn('apk_setting_vend.vend_id', 'vends.id'))
            ->get()
            ->each(function (Vend $vend) use ($service) {
                try {
                    $service->syncSettingsToVend($vend);
                } catch (\Throwable $e) {
                    Log::warning('Happy Hour welcome nudge failed', ['vend' => $vend->code, 'error' => $e->getMessage()]);
                }
            });
    }

    private static function inActiveCampaign(Vend $vend): bool
    {
        return HappyHourCampaign::query()
            ->withoutGlobalScope(OperatorHappyHourCampaignScope::class) // the terminal calls with no user
            ->active()
            ->whereHas('vends', fn ($q) => $q->where('vends.id', $vend->id))
            ->exists();
    }

    /** @return array<string, mixed> */
    private static function payload(bool $welcome): array
    {
        return array_merge(ApkSettingParameters::defaults()->toArray(), [
            'enableWelcomeScene' => $welcome ? 'true' : 'false',
        ]);
    }
}
