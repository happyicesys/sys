<?php

namespace App\Services\HappyHour;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The failsafe: a machine bound to a UI Setting runs the UI Setting only — Happy Hour is IGNORED there
 * (Brian, 2026-10-15, widening 2026-10-12's "only while the UI Setting carries a live campaign"). The
 * UI Setting is the one source of what such a machine shows, so the two promo systems never share a
 * cart or a screen.
 *
 * A machine is paused while it has ANY `apk_setting_vend` row, whatever the UI Setting holds. When that
 * UI Setting also carries a LIVE campaign (`apk_setting_campaign`: `campaigns.is_active = 1`, started,
 * `end_at` null or on/after today) the message names it, longest-running first. A freezer has no UI
 * Setting, so it is never paused, and a machine with none keeps Happy Hour (and gets the bear from
 * HappyHourWelcomeFallback).
 *
 * Read by the planner (no slot while paused, a live slot ended early) and by the campaign page (the
 * exclamation icon and the form warning). Derived on every read, never stored, so Happy Hour resumes
 * at the next slot by itself once the machine is unbound from its UI Setting. Two queries per call,
 * whatever the number of machines.
 */
final class HappyHourPause
{
    /**
     * Why each of these machines is paused now; machines that are not paused are absent.
     *
     * @param  iterable<int|string>  $vendIds
     * @return array<int, array{apk_setting: string, campaign: ?string, ends_on: ?string, message: string}>
     */
    public static function forVends(iterable $vendIds, CarbonInterface $now, array $codes = []): array
    {
        $ids = collect($vendIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $bound = DB::table('apk_setting_vend as sv')
            ->join('apk_settings as s', 's.id', '=', 'sv.apk_setting_id')
            ->whereIn('sv.vend_id', $ids)
            ->orderBy('sv.vend_id')->orderBy('s.id')
            ->get(['sv.vend_id', 'sv.apk_setting_id', 's.name as apk_setting']);
        if ($bound->isEmpty()) {
            return [];
        }

        // The live campaign on each of those UI Settings, for the message only.
        $live = DB::table('apk_setting_campaign as sc')
            ->join('campaigns as c', 'c.id', '=', 'sc.campaign_id')
            ->whereIn('sc.apk_setting_id', $bound->pluck('apk_setting_id')->unique()->values())
            ->where('c.is_active', 1)
            ->where(fn ($q) => $q->whereNull('c.start_at')->orWhere('c.start_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('c.end_at')->orWhere('c.end_at', '>=', $now->copy()->startOfDay()))
            // The campaign that runs longest is the one worth naming: no end first.
            ->orderByRaw('c.end_at IS NULL DESC')->orderByDesc('c.end_at')->orderBy('c.id')
            ->get(['sc.apk_setting_id', 'c.name as campaign', 'c.end_at'])
            ->unique('apk_setting_id')
            ->keyBy('apk_setting_id');

        $paused = [];
        foreach ($bound as $row) {
            $vendId = (int) $row->vend_id;
            if (isset($paused[$vendId])) {
                continue;
            }
            $campaign = $live->get($row->apk_setting_id);
            $endsOn = $campaign?->end_at ? Carbon::parse($campaign->end_at)->toDateString() : null;
            $paused[$vendId] = [
                'apk_setting' => (string) $row->apk_setting,
                'campaign' => $campaign ? (string) $campaign->campaign : null,
                'ends_on' => $endsOn,
                'message' => self::message($codes[$vendId] ?? (string) $vendId, (string) $row->apk_setting, $campaign ? (string) $campaign->campaign : null, $endsOn),
            ];
        }

        return $paused;
    }

    public static function isPaused(int $vendId, CarbonInterface $now): bool
    {
        return self::forVends([$vendId], $now) !== [];
    }

    /** The campaign page's hover text for a paused machine chip, and the form's warning. */
    public static function message(string $machine, string $apkSetting, ?string $campaign, ?string $endsOn): string
    {
        $live = '';
        if ($campaign !== null) {
            $until = $endsOn ? 'until '.Carbon::parse($endsOn)->format('j M Y') : 'no end date';
            $live = " (live campaign '{$campaign}', {$until})";
        }

        return "Happy Hour is ignored on {$machine} — it runs UI Setting '{$apkSetting}'{$live}. "
            .'Unbind the UI Setting to run Happy Hour here.';
    }
}
