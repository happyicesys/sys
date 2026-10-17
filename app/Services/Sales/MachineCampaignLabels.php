<?php

namespace App\Services\Sales;

use App\Models\Vend;
use App\Services\HappyHour\HappyHourEligibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What each machine runs NOW, for the label under Site on Sales Transactions (Brian, 2026-10-17:
 * "similar as in Dashboard"). It is the machine's current campaign, not the one that priced a
 * sale — that is the per-sale "★ HH n%" in Campaign Label(s).
 *
 * - `campaigns`: names of the live UI Setting campaigns (apk_setting_vend → apk_setting_campaign),
 *   the Dashboard's rule: active and not past end_at.
 * - `happy_hour`: true when the machine is in a Happy Hour campaign in force today, can run it
 *   (HappyHourEligibility) and has no UI Setting — a machine bound to ANY UI Setting ignores
 *   Happy Hour (HappyHourPause), so only the pink labels show there.
 *
 * Two queries plus one for the vends, whatever the page size.
 */
class MachineCampaignLabels
{
    /** @return array<int, array{happy_hour: bool, campaigns: list<string>}> keyed by vend id */
    public function forVends(iterable $vendIds): array
    {
        $ids = collect($vendIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $now = Carbon::now();
        $withUiSetting = DB::table('apk_setting_vend')
            ->whereIn('vend_id', $ids)
            ->distinct()
            ->pluck('vend_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $campaigns = DB::table('apk_setting_vend as sv')
            ->join('apk_setting_campaign as sc', 'sc.apk_setting_id', '=', 'sv.apk_setting_id')
            ->join('campaigns as c', 'c.id', '=', 'sc.campaign_id')
            ->whereIn('sv.vend_id', $ids)
            ->where('c.is_active', true)
            ->where(fn ($q) => $q->whereNull('c.end_at')->orWhere('c.end_at', '>=', $now->copy()->startOfDay()))
            ->orderBy('c.name')
            ->get(['sv.vend_id', 'c.id', 'c.name'])
            ->groupBy('vend_id')
            ->map(fn ($rows) => $rows->unique('id')->pluck('name')->values()->all());

        $inHappyHour = Vend::query()
            ->whereIn('id', $ids->reject(fn ($id) => $withUiSetting->has($id)))
            ->whereHas('happyHourCampaigns', fn ($q) => $q->inForce($now))
            ->get(['id', 'is_active', 'machine_type', 'apk_version_code', 'apk_ver_json', 'apk_checked_in_at'])
            ->filter(fn (Vend $vend) => HappyHourEligibility::supports($vend))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        return $ids->mapWithKeys(fn ($id) => [$id => [
            'happy_hour' => $inHappyHour->has($id),
            'campaigns' => $campaigns->get($id, []),
        ]])->all();
    }
}
