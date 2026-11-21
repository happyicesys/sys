<?php

namespace App\Http\Controllers;

use App\Models\GrabMenuSync;
use App\Models\GrabMenuSyncStore;
use App\Models\Operator;
use App\Services\Grab\Menu\GrabMenuSyncTracker;
use App\Services\Grab\Menu\GrabMenuSyncView;
use App\Support\Grab\GrabStoreVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Grab Menu Sync (Brian, 2026-11-21): every change sent to Grab and whether each store has it. One row per change
 * (GrabMenuSync), opened to one line per store (GrabMenuSyncStore). HIPL staff see every operator's stores, anyone
 * else only their own (GrabStoreVisibility), and a change's counts are of the stores the viewer may see.
 */
class GrabMenuSyncController extends Controller
{
    const PER_PAGE = 20;

    public function index(Request $request)
    {
        $visible = GrabStoreVisibility::operatorIds();
        $operatorIds = GrabStoreVisibility::narrow($request->input('operator_id'));
        $state = in_array($request->input('state'), ['attention', 'in_progress', 'synced'], true) ? $request->input('state') : 'all';

        $syncs = GrabMenuSync::query()
            ->with(['user:id,name', 'operator:id,code'])
            // A change is the viewer's when it names one of their operators or reached one of their stores.
            ->where(fn ($query) => $query->whereIn('operator_id', $operatorIds)
                ->orWhereExists(fn ($stores) => $this->storesOf($stores, $operatorIds)))
            ->when($state === 'attention', fn ($query) => $query->whereExists(fn ($stores) => $this->storesOf($stores, $operatorIds)
                ->whereIn('status', [GrabMenuSyncStore::STATUS_FAILED, GrabMenuSyncStore::STATUS_TURNED_AWAY])))
            ->when($state === 'in_progress', fn ($query) => $query->whereExists(fn ($stores) => $this->storesOf($stores, $operatorIds)
                ->whereIn('status', GrabMenuSyncStore::OPEN)))
            ->when($state === 'synced', fn ($query) => $query
                ->whereExists(fn ($stores) => $this->storesOf($stores, $operatorIds))
                ->whereNotExists(fn ($stores) => $this->storesOf($stores, $operatorIds)
                    ->whereNotIn('status', [GrabMenuSyncStore::STATUS_SYNCED, GrabMenuSyncStore::STATUS_SKIPPED])))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $counts = $this->countsFor($syncs->getCollection()->pluck('id'), $operatorIds);

        return Inertia::render('GrabMenu/Sync', [
            'syncs' => [
                'data' => $syncs->getCollection()->map(fn (GrabMenuSync $sync) => $this->syncRow($sync, $counts[$sync->id] ?? collect()))->values(),
                'links' => ['prev' => $syncs->previousPageUrl(), 'next' => $syncs->nextPageUrl()],
                'meta' => ['from' => $syncs->firstItem(), 'to' => $syncs->lastItem(), 'total' => $syncs->total(), 'links' => $syncs->linkCollection()],
            ],
            'operators' => Operator::withoutGlobalScopes()->whereIn('id', $visible)
                ->whereIn('id', DB::table('delivery_platform_ref_numbers')->whereNotNull('operator_id')->distinct()->select('operator_id'))
                ->orderByRaw("code = 'HIPL' DESC")->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => ['operator_id' => $request->input('operator_id'), 'state' => $state],
            'can' => ['update' => $request->user()->can('update delivery-platform-product-mappings')],
            'lastCheckAt' => GrabMenuSyncStore::query()->whereIn('operator_id', $operatorIds)->max('last_checked_at'),
        ]);
    }

    // One change opened: one line per store the viewer may see. Polled by the page while any is still moving.
    public function stores(Request $request, int $id)
    {
        $sync = $this->visibleSync($id);
        $operatorIds = GrabStoreVisibility::operatorIds();

        $rows = GrabMenuSyncStore::query()
            ->with(['binding' => fn ($query) => $query->select('id', 'vend_id', 'vend_code', 'customer_id', 'delivery_platform_ref_number_id')])
            ->where('grab_menu_sync_id', $sync->id)
            ->whereIn('operator_id', $operatorIds)
            ->get()
            ->sortBy([fn ($a, $b) => GrabMenuSyncView::ORDER[$a->status] <=> GrabMenuSyncView::ORDER[$b->status], fn ($a, $b) => strnatcmp((string) $a->vend_code, (string) $b->vend_code)])
            ->values();

        return response()->json([
            'sync' => $this->syncRow($sync->load(['user:id,name', 'operator:id,code']), $rows->countBy('status')),
            'stores' => $rows->map(fn (GrabMenuSyncStore $row) => GrabMenuSyncView::storeRow($row) + ['site' => $row->binding?->siteName()]),
        ]);
    }

    // Ask Grab again for every store of a change that is not on Grab (turned away or not synced).
    public function resend(Request $request, int $id, GrabMenuSyncTracker $tracker)
    {
        $this->mayUpdate();
        $sync = $this->visibleSync($id);
        $rows = GrabMenuSyncStore::query()->with('binding')
            ->where('grab_menu_sync_id', $sync->id)
            ->whereIn('operator_id', GrabStoreVisibility::operatorIds())
            ->whereIn('status', [GrabMenuSyncStore::STATUS_FAILED, GrabMenuSyncStore::STATUS_TURNED_AWAY])
            ->get();
        $count = $tracker->resend($rows);

        return response()->json(['sent' => $count, 'message' => trans_choice('Asked Grab again for :count store.|Asked Grab again for :count stores.', $count, ['count' => $count])]);
    }

    public function resendStore(Request $request, int $rowId, GrabMenuSyncTracker $tracker)
    {
        $this->mayUpdate();
        $row = GrabMenuSyncStore::query()->with('binding')->findOrFail($rowId);
        abort_unless(GrabStoreVisibility::allows((int) $row->operator_id), 403);
        abort_if($row->status === GrabMenuSyncStore::STATUS_SYNCED, 422, __('This store already has it.'));

        $tracker->resend(collect([$row]));

        return response()->json(['message' => __('Asked Grab again for store :code.', ['code' => $row->vend_code])]);
    }

    // Run the system's check now (in the background) for the operators the viewer may see.
    public function checkNow()
    {
        $this->mayUpdate();
        Artisan::queue('grab:check-menu-sync', GrabStoreVisibility::isHipl() ? [] : ['--operator' => GrabStoreVisibility::operatorIds()])->onQueue('default');

        return response()->json(['message' => __('Checking every store now. Results appear here within a minute or two.')]);
    }

    public function latest(Request $request)
    {
        $request->validate(['binding_ids' => ['array'], 'binding_ids.*' => ['integer']]);
        $operatorIds = GrabStoreVisibility::operatorIds();
        $bindingIds = GrabMenuSyncStore::query()->whereIn('delivery_product_mapping_vend_id', $request->input('binding_ids', []))
            ->whereIn('operator_id', $operatorIds)->distinct()->pluck('delivery_product_mapping_vend_id')->all();

        return response()->json(GrabMenuSyncView::latestByBinding($bindingIds));
    }

    private function syncRow(GrabMenuSync $sync, Collection $counts): array
    {
        $total = (int) $counts->sum();
        $synced = (int) ($counts[GrabMenuSyncStore::STATUS_SYNCED] ?? 0);
        $skipped = (int) ($counts[GrabMenuSyncStore::STATUS_SKIPPED] ?? 0);
        $failed = (int) ($counts[GrabMenuSyncStore::STATUS_FAILED] ?? 0);
        $turnedAway = (int) ($counts[GrabMenuSyncStore::STATUS_TURNED_AWAY] ?? 0);
        $moving = collect([GrabMenuSyncStore::STATUS_WAITING, GrabMenuSyncStore::STATUS_SENT, GrabMenuSyncStore::STATUS_PULLED])
            ->sum(fn ($status) => (int) ($counts[$status] ?? 0));

        return [
            'id' => $sync->id,
            'created_at' => $sync->created_at?->toIso8601String(),
            'by' => $sync->user?->name,
            'operator' => $sync->operator?->code,
            'kind' => $sync->kind,
            'summary' => $sync->summary,
            'counts' => [
                'total' => $total,
                'synced' => $synced,
                'moving' => $moving,
                'turned_away' => $turnedAway,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
            // none = no store was asked; synced = every store asked has it; failed = a store is not on Grab and
            // nothing will ask again; in_progress otherwise (turned-away stores are asked again on their own).
            'state' => $total === 0 ? 'none' : ($failed > 0 ? 'failed' : ($moving + $turnedAway > 0 ? 'in_progress' : 'synced')),
        ];
    }

    /** @return Collection<int, Collection<string, int>> by sync id: status => count */
    private function countsFor(Collection $syncIds, array $operatorIds): Collection
    {
        if ($syncIds->isEmpty()) {
            return collect();
        }

        return GrabMenuSyncStore::query()
            ->whereIn('grab_menu_sync_id', $syncIds)
            ->whereIn('operator_id', $operatorIds)
            ->groupBy('grab_menu_sync_id', 'status')
            ->selectRaw('grab_menu_sync_id, status, COUNT(*) AS n')
            ->get()
            ->groupBy('grab_menu_sync_id')
            ->map(fn ($rows) => $rows->pluck('n', 'status')->map(fn ($n) => (int) $n));
    }

    private function storesOf($query, array $operatorIds)
    {
        return $query->from('grab_menu_sync_stores')
            ->whereColumn('grab_menu_sync_stores.grab_menu_sync_id', 'grab_menu_syncs.id')
            ->whereIn('grab_menu_sync_stores.operator_id', $operatorIds);
    }

    private function mayUpdate(): void
    {
        abort_unless(auth()->user()->can('update delivery-platform-product-mappings'), 403);
    }

    private function visibleSync(int $id): GrabMenuSync
    {
        $sync = GrabMenuSync::query()->findOrFail($id);
        $visible = GrabStoreVisibility::operatorIds();
        abort_unless(
            in_array((int) $sync->operator_id, $visible, true)
            || GrabMenuSyncStore::query()->where('grab_menu_sync_id', $sync->id)->whereIn('operator_id', $visible)->exists(),
            403,
        );

        return $sync;
    }
}
