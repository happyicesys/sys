<?php

namespace App\Http\Controllers;

use App\Http\Requests\HappyHour\HappyHourCampaignRequest;
use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Product;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\HappyHour\HappyHourPlanner;
use App\Services\HappyHour\HappyHourPricing;
use App\Services\HappyHour\HappyHourRanker;
use App\Services\UserLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Machine Management → Happy Hour Campaign. CRUD on campaigns and their machines; every save hands
 * the schedule to HappyHourPlanner at once (cancel what no longer applies, plan what does), so the
 * freezers hear about it within seconds instead of at the next minute's run.
 */
class HappyHourCampaignController extends Controller
{
    /** Fields whose change re-plans the schedule (a rename does not). */
    private const SCHEDULE_FIELDS = [
        'is_active', 'starts_on', 'ends_on', 'days_mask', 'window_start', 'window_end', 'slot_minutes',
        'sku_count', 'selection_rule', 'lookback_days', 'discount_pct', 'min_balance_pct', 'min_qty',
        'price_step_cents', 'allow_below_cost', 'excluded_product_ids',
    ];

    public function __construct(private readonly HappyHourPlanner $planner) {}

    public function index(): Response
    {
        $now = Carbon::now();
        $campaigns = HappyHourCampaign::query()
            // Only the viewer's own machines are listed (a HIPL campaign may span operators).
            ->with(['vends' => fn ($q) => $this->visibleVends($q)
                ->select('vends.id', 'vends.code', 'vends.code_prefix', 'vends.machine_type', 'vends.is_active', 'vends.apk_version_code')])
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
            ->get()
            ->groupBy('happy_hour_campaign_id');

        return Inertia::render('HappyHourCampaign/Index', [
            'campaigns' => $campaigns->map(fn (HappyHourCampaign $c) => $this->campaignPayload($c, $slots->get($c->id, collect())))->values(),
            'machines' => $this->machineOptions(),
            'products' => $this->productOptions(),
            'rules' => collect(HappyHourCampaign::RULES)->map(fn ($label, $id) => ['id' => $id, 'name' => $label])->values(),
            'minApkVersion' => (int) config('happy_hour.min_freezer_apk_version', 30),
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
        $this->planNow($campaign);

        return back()->with('success', "Happy Hour \"{$campaign->name}\" saved.");
    }

    public function update(HappyHourCampaignRequest $request, int $id): RedirectResponse
    {
        $campaign = HappyHourCampaign::findOrFail($id);
        $now = Carbon::now();

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
        $this->planNow($campaign->fresh());

        return back()->with('success', "Happy Hour \"{$campaign->name}\" updated.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $campaign = HappyHourCampaign::findOrFail($id);
        $this->planner->cancel($campaign, Carbon::now());
        $campaign->delete();

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

        return response()->json([
            'campaign' => $campaign->name,
            'at' => $now->toDateTimeString(),
            'machines' => $vends->map(fn (Vend $vend) => [
                'vend_id' => $vend->id,
                'code' => $vend->codeLabel(),
                'supported' => HappyHourPricing::supports($vend),
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
        $before = $campaign->vends()->pluck('vends.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        // The form only lists the viewer's machines, so a machine it cannot see stays bound: an
        // operator's save must never take another operator's freezer out of a campaign.
        $visible = $this->visibleVends($campaign->vends())->pluck('vends.id')->map(fn ($id) => (int) $id)->all();
        $after = collect($vendIds)->map(fn ($id) => (int) $id)
            ->merge(array_diff($before, $visible))
            ->unique()->sort()->values()->all();
        if ($before === $after) {
            return [];
        }
        $campaign->vends()->sync($after);
        // A pivot sync fires no model event, so the audit trail is written by hand.
        UserLogger::recordChanges($campaign, ['vend_ids' => [$before, $after]]);

        return array_values(array_diff($before, $after));
    }

    /** @return array<string, mixed> */
    private function campaignPayload(HappyHourCampaign $c, $liveSlots): array
    {
        $now = Carbon::now();

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
            'selection_rule' => $c->selection_rule,
            'lookback_days' => $c->lookback_days,
            'discount_pct' => $c->discount_pct,
            'min_balance_pct' => $c->min_balance_pct,
            'min_qty' => $c->min_qty,
            'price_step_cents' => $c->price_step_cents,
            'allow_below_cost' => $c->allow_below_cost,
            'excluded_product_ids' => $c->excludedProductIds(),
            'vends' => $c->vends->map(fn (Vend $v) => [
                'id' => $v->id,
                'code' => $v->codeLabel(),
                'supported' => HappyHourPricing::supports($v),
                // For the ⚠ tooltip: why an unsupported machine gets no slots.
                'is_active' => (bool) $v->is_active,
                'apk_version_code' => $v->apk_version_code,
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
            ])->values(),
        ];
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

    /** Smart freezers the viewer can see (Vend's operator scope applies), with app-version readiness. */
    private function machineOptions(): array
    {
        return Vend::query()
            ->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)
            ->orderBy('code')
            ->get(['id', 'code', 'code_prefix', 'machine_type', 'is_active', 'apk_version_code'])
            ->map(fn (Vend $v) => [
                'id' => $v->id,
                'name' => $v->codeLabel().($v->is_active ? '' : ' (inactive)'),
                'apk_version_code' => $v->apk_version_code,
                'supported' => HappyHourPricing::supports($v),
            ])->values()->all();
    }

    /** Products on any smart freezer's planogram, for the exclusion picker. */
    private function productOptions(): array
    {
        $ids = DB::table('vend_channels')
            ->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vends.machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)
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
