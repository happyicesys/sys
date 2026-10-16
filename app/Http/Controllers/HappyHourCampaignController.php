<?php

namespace App\Http\Controllers;

use App\Http\Requests\HappyHour\HappyHourCampaignRequest;
use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Product;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\HappyHour\HappyHourEligibility;
use App\Services\HappyHour\HappyHourMachineGroups;
use App\Services\HappyHour\HappyHourPause;
use App\Services\HappyHour\HappyHourPlanner;
use App\Services\HappyHour\HappyHourRanker;
use App\Services\HappyHour\HappyHourSales;
use App\Services\HappyHour\HappyHourWelcomeFallback;
use App\Services\UserLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Machine Management → Happy Hour Campaign. CRUD on campaigns and their machines (smart freezers, and
 * big-board vending machines on app 315+); every save hands the schedule to HappyHourPlanner at once
 * (cancel what no longer applies, plan what does), so the machines hear about it within seconds
 * instead of at the next minute's run.
 */
class HappyHourCampaignController extends Controller
{
    /** Fields whose change re-plans the schedule (a rename does not). */
    private const SCHEDULE_FIELDS = [
        'is_active', 'starts_on', 'ends_on', 'days_mask', 'window_start', 'window_end', 'slot_minutes',
        'sku_count', 'skus_per_slot', 'selection_rule', 'lookback_days', 'discount_pct', 'min_balance_pct', 'min_qty',
        'price_step_cents', 'allow_below_cost', 'skip_unavailable', 'excluded_product_ids',
    ];

    public function __construct(private readonly HappyHourPlanner $planner) {}

    public function index(): Response
    {
        $now = Carbon::now();
        $campaigns = HappyHourCampaign::query()
            // Only the viewer's own machines are listed (a HIPL campaign may span operators).
            ->with(['vends' => fn ($q) => $this->visibleVends($q)
                ->select('vends.id', 'vends.code', 'vends.code_prefix', 'vends.machine_type', 'vends.is_active', 'vends.apk_version_code', 'vends.apk_ver_json',
                    'vends.customer_id', 'vends.is_using_server_price')
                // The Status box names each machine's Site and the RP it sells at (Brian, 2026-10-15).
                ->with('customer:id,name,selling_price_type')])
            ->orderByDesc('is_active')->orderByDesc('id')
            ->get();
        // Only the slot each machine is running now (the Status column); one per machine at most.
        $slots = HappyHourSlot::query()
            ->open()
            ->with('product:id,code,name')
            ->whereIn('happy_hour_campaign_id', $campaigns->pluck('id'))
            ->whereIn('vend_id', $campaigns->flatMap(fn ($c) => $c->vends->pluck('id'))->unique()->values())
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->orderBy('vend_id')
            ->get();
        $stock = $this->liveStock($slots);
        // Units sold so far in each live slot ("HH: n") and each live machine's last 24 h (Brian, 2026-10-15).
        $grace = (int) config('happy_hour.sale_grace_seconds', 300);
        $sold = app(HappyHourSales::class)->slotTotals($slots->pluck('vend_id'), $now->copy()->subDay(), $now->copy()->addSeconds($grace));
        $slots = $slots->groupBy('happy_hour_campaign_id');

        $machines = $this->machineOptions($now);
        // The failsafe (a machine bound to a UI Setting ignores Happy Hour), for the chips' icon.
        $boundVends = $campaigns->flatMap(fn ($c) => $c->vends);
        $paused = HappyHourPause::forVends($boundVends->pluck('id'), $now, $boundVends->mapWithKeys(fn (Vend $v) => [$v->id => $v->codeLabel()])->all());

        return Inertia::render('HappyHourCampaign/Index', [
            'campaigns' => $campaigns->map(fn (HappyHourCampaign $c) => $this->campaignPayload($c, $slots->get($c->id, collect()), $paused, $stock, $sold))->values(),
            'machines' => $machines,
            'products' => $this->productOptions(collect($machines)->where('machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE)->pluck('id')->all()),
            'rules' => collect(HappyHourCampaign::RULES)->map(fn ($label, $id) => ['id' => $id, 'name' => $label])->values(),
            'minApkVersion' => HappyHourEligibility::minFreezerApk(),
            'minVendingApkVersion' => HappyHourEligibility::minVendingApk(),
            'machineGroups' => collect(HappyHourMachineGroups::GROUPS)->map(fn ($label, $id) => ['id' => $id, 'name' => $label])->values(),
        ]);
    }

    public function store(HappyHourCampaignRequest $request): RedirectResponse
    {
        $campaign = DB::transaction(function () use ($request) {
            $campaign = HappyHourCampaign::create([
                ...$this->fields($request),
                // The owner (OperatorHappyHourCampaignScope): the creator's operator, HIPL when unset.
                'operator_id' => (int) auth()->user()->operator_id ?: OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            $this->syncVends($campaign, $request->input('vend_ids', []));

            return $campaign;
        });
        // A machine picked by name leaves any group campaign now, before this one plans it.
        $this->followGroups();
        $this->planNow($campaign);
        HappyHourWelcomeFallback::nudgeUnbound($this->memberIds($campaign));

        return back()->with('success', "Happy Hour \"{$campaign->name}\" saved.");
    }

    public function update(HappyHourCampaignRequest $request, int $id): RedirectResponse
    {
        $campaign = HappyHourCampaign::findOrFail($id);
        $now = Carbon::now();
        $membersBefore = $this->memberIds($campaign);

        DB::transaction(function () use ($request, $campaign, $now) {
            $campaign->fill([...$this->fields($request), 'updated_by' => auth()->id()]);
            $reschedule = $campaign->isDirty(self::SCHEDULE_FIELDS);
            $campaign->save();
            $removed = $this->syncVends($campaign, $request->input('vend_ids', []));

            if ($reschedule) {
                $this->planner->cancel($campaign, $now);
            } elseif ($removed !== []) {
                $this->planner->cancel($campaign, $now, $removed);
            }
        });
        // Picked by name now (or no longer): group campaigns give the machine up / take it back now.
        $this->followGroups();
        $this->planNow($campaign->fresh());
        // Machines added, kept or taken out: those without a UI Setting re-read /parameters, so
        // the bear comes on or goes off now (HappyHourWelcomeFallback).
        HappyHourWelcomeFallback::nudgeUnbound([...$membersBefore, ...$this->memberIds($campaign)]);

        return back()->with('success', "Happy Hour \"{$campaign->name}\" updated.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $campaign = HappyHourCampaign::findOrFail($id);
        $members = $this->memberIds($campaign);
        $this->planner->cancel($campaign, Carbon::now());
        $campaign->delete();
        // Its machines picked by name go back to any group campaign that covers them.
        $this->followGroups();
        HappyHourWelcomeFallback::nudgeUnbound($members);

        return back()->with('success', "Happy Hour \"{$campaign->name}\" deleted.");
    }

    /**
     * "Lineup now" (a machine chip on the page): every SKU with its rank or the reason it is left
     * out. `vend_id` ranks just that machine; without it, every bound machine.
     */
    public function preview(Request $request, int $id, HappyHourRanker $ranker): JsonResponse
    {
        $campaign = HappyHourCampaign::findOrFail($id);
        $vends = $this->visibleVends($campaign->vends())
            ->when($request->filled('vend_id'), fn ($q) => $q->where('vends.id', (int) $request->input('vend_id')))
            ->get();
        $now = Carbon::now();
        $paused = HappyHourPause::forVends($vends->pluck('id'), $now, $vends->mapWithKeys(fn (Vend $v) => [$v->id => $v->codeLabel()])->all());

        return response()->json([
            'campaign' => $campaign->name,
            'at' => $now->toDateTimeString(),
            'machines' => $vends->map(fn (Vend $vend) => [
                'vend_id' => $vend->id,
                'code' => $vend->codeLabel(),
                'supported' => HappyHourEligibility::supports($vend),
                'unsupported_reason' => HappyHourEligibility::reason($vend),
                'paused' => $paused[$vend->id]['message'] ?? null,
                'apk_version_code' => $vend->apk_version_code,
                'candidates' => array_map(fn ($c) => $c->toArray(), $ranker->rank($campaign, $vend, $now)),
            ])->values(),
        ]);
    }

    /**
     * The viewer's own machines among a campaign's (`campaign->vends()` drops Vend's global scopes):
     * HIPL sees every machine, an operator-restricted viewer only its operator's.
     */
    private function visibleVends($query)
    {
        $operatorId = OperatorVendFilterScope::viewerOperatorId();

        return $query->when($operatorId, fn ($q) => $q->where('vends.operator_id', $operatorId));
    }

    /**
     * Every machine in the campaign, whoever can see it (the pivot read directly: the Vend scope
     * would hide another operator's machine from this viewer).
     *
     * @return list<int>
     */
    private function memberIds(HappyHourCampaign $campaign): array
    {
        return DB::table('happy_hour_campaign_vend')->where('happy_hour_campaign_id', $campaign->id)
            ->pluck('vend_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<array{code: string, by: string}> */
    private function overridden(HappyHourCampaign $c): array
    {
        $byVend = app(HappyHourMachineGroups::class)->overridden($c);
        if ($byVend === []) {
            return [];
        }

        return $this->visibleVends(Vend::withoutGlobalScopes()->whereIn('vends.id', array_keys($byVend)))
            ->orderBy('code')->get(['id', 'code', 'code_prefix'])
            ->map(fn (Vend $v) => ['code' => $v->codeLabel(), 'by' => $byVend[$v->id]])
            ->values()->all();
    }

    /** Group campaigns follow their members now; a hiccup here waits for the minute run. */
    private function followGroups(): void
    {
        try {
            $this->planner->followGroups(Carbon::now());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Plans at once so the freezers hear within seconds. The campaign is already saved: if planning
     * fails here (a cache or queue hiccup), the minute run plans it instead of the save erroring.
     */
    private function planNow(HappyHourCampaign $campaign): void
    {
        try {
            $this->planner->planCampaign($campaign, Carbon::now());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, mixed> */
    private function fields(HappyHourCampaignRequest $request): array
    {
        $data = $request->safe()->except('vend_ids');
        $data['excluded_product_ids'] = array_values(array_map('intval', $data['excluded_product_ids'] ?? [])) ?: null;
        $data['machine_groups'] = array_values(array_unique($data['machine_groups'] ?? [])) ?: null;
        // Stored as TIME ("14:00:00"); match it so an unchanged window is not seen as an edit.
        $data['window_start'] .= ':00';
        $data['window_end'] .= ':00';

        return $data;
    }

    /**
     * @param  array<int, mixed>  $vendIds
     * @return list<int> the machines taken out of the campaign
     */
    private function syncVends(HappyHourCampaign $campaign, array $vendIds): array
    {
        // Hand-picked rows only: a live group's members are HappyHourMachineGroups' to keep.
        $before = $campaign->vends()->wherePivotNull('via_group')->pluck('vends.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        // The form only lists the viewer's machines, so a machine it cannot see stays bound: an
        // operator's save must never take another operator's freezer out of a campaign.
        $visible = $this->visibleVends($campaign->vends()->wherePivotNull('via_group'))->pluck('vends.id')->map(fn ($id) => (int) $id)->all();
        $after = collect($vendIds)->map(fn ($id) => (int) $id)
            ->merge(array_diff($before, $visible))
            ->unique()->sort()->values()->all();
        $result = app(HappyHourMachineGroups::class)->apply($campaign, $after);
        if ($before !== $after) {
            // A pivot write fires no model event, so the audit trail is written by hand.
            UserLogger::recordChanges($campaign, ['vend_ids' => [$before, $after]]);
        }

        return $result['removed'];
    }

    /**
     * @param  array<int, array{message: string}>  $paused  HappyHourPause::forVends()
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, array{qty: int, capacity: ?int}>  $stock  "vend:product" => on hand now (liveStock)
     * @param  array<int, array{vend_id: int, units: int, revenue: int, discount: int}>  $sold  HappyHourSales::slotTotals, last 24 h
     */
    private function campaignPayload(HappyHourCampaign $c, $liveSlots, array $paused = [], array $stock = [], array $sold = []): array
    {
        $now = Carbon::now();
        // A machine's last 24 h = every slot it ran, whichever campaign (one machine, one campaign at a time).
        $last24h = collect($sold)->groupBy('vend_id')->map(fn ($rows) => [
            'units' => (int) $rows->sum('units'),
            'discount' => (int) $rows->sum('discount'),
        ]);

        return [
            'id' => $c->id,
            'name' => $c->name,
            'is_active' => $c->is_active,
            'starts_on' => $c->starts_on?->toDateString(),
            'ends_on' => $c->ends_on?->toDateString(),
            'days_mask' => $c->days_mask,
            'days_label' => $c->daysLabel(),
            'window_start' => substr((string) $c->window_start, 0, 5),
            'window_end' => substr((string) $c->window_end, 0, 5),
            'slot_minutes' => $c->slot_minutes,
            'sku_count' => $c->sku_count,
            'skus_per_slot' => $c->skus_per_slot,
            'selection_rule' => $c->selection_rule,
            'lookback_days' => $c->lookback_days,
            'discount_pct' => $c->discount_pct,
            'min_balance_pct' => $c->min_balance_pct,
            'min_qty' => $c->min_qty,
            'price_step_cents' => $c->price_step_cents,
            'allow_below_cost' => $c->allow_below_cost,
            'skip_unavailable' => $c->skip_unavailable,
            'excluded_product_ids' => $c->excludedProductIds(),
            'machine_groups' => array_values((array) $c->machine_groups),
            // Group members another campaign picked by name — they run that one while it is in force.
            'overridden' => $this->overridden($c),
            'vends' => $c->vends->map(fn (Vend $v) => [
                'id' => $v->id,
                // NULL = picked by hand; else the live group it joined through.
                'via_group' => $v->pivot?->via_group,
                'code' => $v->codeLabel(),
                'site' => $v->customer?->name,
                'customer_id' => $v->customer_id,
                // The Site's tier (the Site owns it); board_price = the machine does not follow it.
                'rp' => ($rp = $v->customer?->selling_price_type) ? "RP{$rp}" : null,
                'board_price' => ! $v->usesServerPrice(),
                'machine_type' => $v->machine_type,
                'supported' => HappyHourEligibility::supports($v),
                // For the ⚠ tooltip: why an unsupported machine gets no slots.
                'is_active' => (bool) $v->is_active,
                'apk_version_code' => $v->apk_version_code,
                'unsupported_reason' => HappyHourEligibility::reason($v),
                // For the ! icon: this machine runs a UI Setting, so Happy Hour is ignored here (HappyHourPause).
                'paused' => $paused[$v->id]['message'] ?? null,
                // Happy Hour units sold and discount given (cents) in the last 24 h, all slots and SKUs.
                'l24h_units' => $last24h[$v->id]['units'] ?? 0,
                'l24h_discount' => $last24h[$v->id]['discount'] ?? 0,
            ])->values(),
            'status' => $this->status($c, $now),
            'live' => $liveSlots->map(fn (HappyHourSlot $s) => [
                'id' => $s->id,
                'vend_id' => $s->vend_id,
                'starts_at' => $s->starts_at->format('H:i'),
                'ends_at' => $s->ends_at->format('H:i'),
                'product' => $s->product?->name ?? "#{$s->product_id}",
                'product_code' => $s->product?->code,
                'original_price' => $s->original_price,
                'promo_price' => $s->promo_price,
                // On hand now / capacity, summed over the SKU's active channels — what the ranker reads.
                'qty' => $stock["{$s->vend_id}:{$s->product_id}"]['qty'] ?? null,
                'capacity' => $stock["{$s->vend_id}:{$s->product_id}"]['capacity'] ?? null,
                // Units sold in THIS slot so far — not a running total; the next slot starts at 0.
                'sold' => $sold[$s->id]['units'] ?? 0,
            ])->values(),
        ];
    }

    /**
     * On-hand qty and capacity of each live slot's SKU on its machine, in one query: the same sums over
     * active channels HappyHourRanker ranks by (a vending SKU may sit in several channels).
     *
     * @return array<string, array{qty: int, capacity: ?int}> "vend:product" => stock
     */
    private function liveStock($slots): array
    {
        if ($slots->isEmpty()) {
            return [];
        }

        return DB::table('vend_channels')
            ->whereIn('vend_id', $slots->pluck('vend_id')->unique()->values())
            ->whereIn('product_id', $slots->pluck('product_id')->unique()->values())
            ->where('is_active', 1)
            ->groupBy('vend_id', 'product_id')
            ->selectRaw('vend_id, product_id, SUM(GREATEST(qty, 0)) AS qty, SUM(GREATEST(capacity, 0)) AS capacity')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->vend_id}:{$row->product_id}" => [
                'qty' => (int) $row->qty,
                'capacity' => (int) $row->capacity > 0 ? (int) $row->capacity : null,
            ]])
            ->all();
    }

    private function status(HappyHourCampaign $c, Carbon $now): string
    {
        if (! $c->is_active) {
            return 'paused';
        }
        if ($c->ends_on !== null && $now->toDateString() > $c->ends_on->toDateString()) {
            return 'ended';
        }
        if ($c->runsOn($now)) {
            [$start, $end] = $c->windowOn($now);
            if ($now->gte($start) && $now->lt($end)) {
                return 'running';
            }
        }

        return 'scheduled';
    }

    /**
     * The machines the viewer can see (Vend's operator scope applies) that may join a campaign: every
     * smart freezer, with app-version readiness, and the vending machines that can run it — big board,
     * app 315+, active (HappyHourEligibility) — plus any vending machine already in a campaign, so an
     * edit never drops one that has since become ineligible. A vending machine whose UI Setting has a
     * live campaign is offered with the pause warning (warn, never block).
     *
     * @return list<array<string, mixed>>
     */
    private function machineOptions(Carbon $now): array
    {
        $columns = ['id', 'code', 'code_prefix', 'machine_type', 'is_active', 'apk_version_code', 'apk_ver_json'];
        $freezers = Vend::query()->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)->get($columns);
        $bound = DB::table('happy_hour_campaign_vend')->distinct()->pluck('vend_id')->map(fn ($id) => (int) $id)->all();
        $vending = Vend::query()->where('machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE)
            ->where(fn ($q) => $q->where('is_active', 1)->orWhereIn('id', $bound))
            ->get($columns)
            ->filter(fn (Vend $v) => HappyHourEligibility::supportsVending($v) || in_array($v->id, $bound, true));
        $machines = $freezers->concat($vending)->sortBy('code')->values();
        $paused = HappyHourPause::forVends($machines->pluck('id'), $now, $machines->mapWithKeys(fn (Vend $v) => [$v->id => $v->codeLabel()])->all());

        return $machines->map(fn (Vend $v) => [
            'id' => $v->id,
            'name' => $v->codeLabel().($v->is_active ? '' : ' (inactive)'),
            'machine_type' => $v->machine_type,
            'apk_version_code' => $v->apk_version_code,
            'supported' => HappyHourEligibility::supports($v),
            'unsupported_reason' => HappyHourEligibility::reason($v),
            'paused' => $paused[$v->id]['message'] ?? null,
        ])->values()->all();
    }

    /**
     * Products on any smart freezer's planogram or on the offered vending machines, for the exclusion
     * picker.
     *
     * @param  list<int>  $vendingIds
     */
    private function productOptions(array $vendingIds = []): array
    {
        $ids = DB::table('vend_channels')
            ->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where(fn ($q) => $q->where('vends.machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)
                ->when($vendingIds !== [], fn ($q) => $q->orWhereIn('vends.id', $vendingIds)))
            // A raw join reaches vends with no global scope: apply the viewer boundary by hand.
            ->when(OperatorVendFilterScope::viewerOperatorId(), fn ($q, $operatorId) => $q->where('vends.operator_id', $operatorId))
            ->whereNotNull('vend_channels.product_id')
            ->distinct()
            ->pluck('vend_channels.product_id');

        return Product::query()->whereIn('id', $ids)->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => trim("{$p->code} {$p->name}")])
            ->values()->all();
    }
}
