<?php

namespace App\Services\HappyHour;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The failsafe (Brian, 2026-10-12): a UI Setting campaign beats Happy Hour, per machine. Ops keep the
 * two apart; if one slips through, the campaign keeps running and Happy Hour stops on that machine,
 * so the two promo systems never share a cart (the vending APK mirrors the rule on its side).
 *
 * A machine is paused while its UI Setting (`apk_setting_vend`) carries a LIVE campaign
 * (`apk_setting_campaign`): `campaigns.is_active = 1`, started (`start_at` <= now, or no start), and not
 * ended (`end_at` null or on/after today — `end_at` is a date at 00:00, the campaign's last day). It
 * does not depend on `enableLabelPromo`, so the rule errs towards pausing. A freezer has no UI
 * Setting, so it is never paused — the rule is written for any machine and costs a freezer nothing.
 *
 * Read by the planner (no slot while paused, a live slot ended early) and by the campaign page (the
 * exclamation icon and the form warning). Derived on every read, never stored, so Happy Hour resumes
 * at the next slot by itself when the campaign ends or is unbound. One query per call, whatever the
 * number of machines.
 */
final class HappyHourPause
{
    /**
     * Why each of these machines is paused now; machines that are not paused are absent.
     *
     * @param  iterable<int|string>  $vendIds
     * @return array<int, array{apk_setting: string, campaign: string, ends_on: ?string, message: string}>
     */
    public static function forVends(iterable $vendIds, CarbonInterface $now, array $codes = []): array
    {
        $ids = collect($vendIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('apk_setting_vend as sv')
            ->join('apk_settings as s', 's.id', '=', 'sv.apk_setting_id')
            ->join('apk_setting_campaign as sc', 'sc.apk_setting_id', '=', 'sv.apk_setting_id')
            ->join('campaigns as c', 'c.id', '=', 'sc.campaign_id')
            ->whereIn('sv.vend_id', $ids)
            ->where('c.is_active', 1)
            ->where(fn ($q) => $q->whereNull('c.start_at')->orWhere('c.start_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('c.end_at')->orWhere('c.end_at', '>=', $now->copy()->startOfDay()))
            // The campaign that keeps Happy Hour away longest is the one worth naming: no end first.
            ->orderByRaw('c.end_at IS NULL DESC')->orderByDesc('c.end_at')->orderBy('c.id')
            ->get(['sv.vend_id', 's.name as apk_setting', 'c.name as campaign', 'c.end_at']);

        $paused = [];
        foreach ($rows as $row) {
            $vendId = (int) $row->vend_id;
            if (isset($paused[$vendId])) {
                continue;
            }
            $endsOn = $row->end_at ? Carbon::parse($row->end_at)->toDateString() : null;
            $paused[$vendId] = [
                'apk_setting' => (string) $row->apk_setting,
                'campaign' => (string) $row->campaign,
                'ends_on' => $endsOn,
                'message' => self::message($codes[$vendId] ?? (string) $vendId, (string) $row->apk_setting, (string) $row->campaign, $endsOn),
            ];
        }

        return $paused;
    }

    public static function isPaused(int $vendId, CarbonInterface $now): bool
    {
        return self::forVends([$vendId], $now) !== [];
    }

    /** The campaign page's hover text for a paused machine chip, and the form's warning. */
    public static function message(string $machine, string $apkSetting, string $campaign, ?string $endsOn): string
    {
        $until = $endsOn ? 'until '.Carbon::parse($endsOn)->format('j M Y') : 'no end date';

        return "Happy Hour paused on {$machine} — UI Setting '{$apkSetting}' has live campaign '{$campaign}' ({$until}). "
            .'Resumes automatically when the campaign ends or is removed.';
    }
}
