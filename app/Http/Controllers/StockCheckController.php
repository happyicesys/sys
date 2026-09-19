<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesOpsJobStops;
use App\Http\Requests\StockCheck\StoreStockCheckRequest;
use App\Http\Requests\StockCheck\SubmitStockCheckRequest;
use App\Http\Resources\StockCheckResource;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\StockCheck;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\StockCheck\StockCheckSampler;
use App\Services\StockCheck\StockCheckService;
use App\Services\StockCheck\Sync\ChannelSyncResult;
use App\Support\VendCode;
use App\Traits\ExportOptimizationTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * "Stock Count" spot checks: count stops inside an ops job. The rules are
 * StockCheckService's; this class gates (permission + the operator ceiling of
 * the check's ops job) and shapes responses.
 *
 * Unrelated to ReportController::indexStockCount (the nightly valuation report).
 */
class StockCheckController extends Controller
{
    use ExportOptimizationTrait;
    use ManagesOpsJobStops;

    private const SORTABLE = ['code', 'status', 'created_at', 'counted_at', 'job_date'];

    private const ATTACHMENT_DIR = 'sys/stock-checks';

    public function __construct(private StockCheckService $service, private StockCheckSampler $sampler)
    {
        $this->middleware('auth');
        $this->middleware(['permission:read stock-checks'])->only(['index', 'edit', 'drawOptions']);
        $this->middleware(['permission:export stock-checks'])->only(['exportExcel']);
        $this->middleware(['permission:create stock-checks'])->only(['store']);
        $this->middleware(['permission:update stock-checks'])->only([
            'update', 'updateSequence', 'submit', 'undo', 'cancel', 'storeAttachment', 'destroyAttachment',
        ]);
        // Re-drawing the sample and writing the result back to the system
        // quantity are supervisor-and-above actions (Brian, 2026-09-19).
        $this->middleware(['permission:redraw stock-checks'])->only(['redraw']);
        $this->middleware(['permission:sync stock-checks'])->only(['sync']);
        $this->middleware(['permission:delete stock-checks'])->only(['destroy']);
    }

    // ---------------------------------------------------------------- listing

    public function index(Request $request)
    {
        $request->validate(['numberPerPage' => ['nullable', 'regex:/^(\d+|All)$/']]);
        $numberPerPage = $request->numberPerPage ?: 100;

        $checks = $this->sorted($this->filtered($request), $request)
            ->with(['vend', 'customer:id,name', 'channels', 'opsJob.deliveredBy:id,name', 'createdBy:id,name', 'countedBy:id,name', 'syncedBy:id,name'])
            ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('StockCheck/Index', [
            'stockChecks' => StockCheckResource::collection($checks),
            'statusOptions' => collect(StockCheck::STATUS_MAPPINGS)->map(fn ($name, $id) => ['id' => (string) $id, 'value' => $name])->values(),
            'driverOptions' => $this->stopDriverOptions(),
            'filters' => $this->filterState($request),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = $this->sorted($this->filtered($request), $request)
            ->with(['vend', 'customer:id,name', 'channels.product:id,code,name', 'opsJob.deliveredBy:id,name', 'countedBy:id,name']);

        // One row per counted channel.
        $rows = (function () use ($query) {
            foreach ($query->lazy(200) as $check) {
                foreach ($check->channels as $channel) {
                    yield [
                        'Stock Count' => $check->display_code,
                        'Job Date' => $check->opsJob?->date?->format('Y-m-d'),
                        'Assigned To' => $check->opsJob?->deliveredBy?->name,
                        'Machine ID' => $check->vend?->codeLabel(),
                        'Site' => $check->customer?->name,
                        'Status' => $check->statusName(),
                        'Channel' => $channel->vend_channel_code,
                        'Product Code' => $channel->product?->code,
                        'Product' => $channel->product?->name,
                        'System Qty' => $channel->system_qty,
                        'Real Qty' => $channel->counted_qty,
                        'Variance Qty' => $channel->variance_qty,
                        // Cents everywhere; divide only here, at the point of display.
                        'Variance Value' => $channel->varianceValueCents() === null ? null : $channel->varianceValueCents() / 100,
                        'Note' => $channel->note,
                        'Counted At' => $check->counted_at?->format('Y-m-d H:i'),
                        'Counted By' => $check->countedBy?->name,
                        'Synced At' => $channel->synced_at?->format('Y-m-d H:i'),
                        'Qty Before Sync' => $channel->qty_before_sync,
                        'Qty After Sync' => $channel->qty_after_sync,
                    ];
                }
            }
        })();

        return (new FastExcel($rows))->download($this->formatExportFilename('StockCounts', 'xlsx'));
    }

    // ------------------------------------------------------------------ check

    /** What the create modal needs about one machine: how many channels can be drawn, holding what. */
    public function drawOptions(int $opsJobId, int $vendId)
    {
        $opsJob = $this->scopedOpsJob($opsJobId);
        $vend = $this->vendVisibleToViewer($vendId);

        $eligible = $this->sampler->eligible(
            VendChannel::query()->where('vend_id', $vend->id)->with('product:id,code,name')->get()
        );

        return response()->json([
            'eligible_count' => $eligible->count(),
            'products' => $eligible->groupBy('product_id')->map(fn ($channels) => [
                'id' => $channels->first()->product_id,
                'value' => trim(($channels->first()->product?->code ?? '').' '.($channels->first()->product?->name ?? '')),
                'channels' => $channels->count(),
            ])->sortBy('value')->values(),
        ]);
    }

    public function store(StoreStockCheckRequest $request, int $opsJobId)
    {
        $opsJob = $this->scopedOpsJob($opsJobId);
        $vend = $this->vendVisibleToViewer($request->integer('vend_id'));

        $check = $this->service->create($opsJob, $vend, [
            'is_random' => $request->boolean('is_random'),
            'sample_size' => $request->input('sample_size'),
            'product_ids' => $request->input('product_ids', []),
            'remarks' => $request->input('remarks'),
            'sequence' => $request->input('sequence'),
        ], $request->user());

        return response()->json([
            'success' => true,
            'id' => $check->id,
            'display_code' => $check->display_code,
            'channels_count' => $check->channels()->count(),
        ]);
    }

    public function edit(int $id)
    {
        $check = $this->loadForEdit($this->scopedCheck($id));
        $target = $this->service->syncTargetFor($check->vend);

        return Inertia::render('StockCheck/Edit', [
            'stockCheck' => new StockCheckResource($check),
            'sync' => [
                'refusal' => $target->refusal($check->vend),
                'notice' => $target->notice($check->vend),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['remarks' => 'nullable|string|max:2000']);

        $this->service->updateHeader($this->scopedCheck($id), $data, $request->user());

        return $this->fresh($id);
    }

    public function updateSequence(Request $request, int $id)
    {
        $data = $request->validate(['sequence' => 'nullable|numeric|min:0.1']);

        $this->service->updateHeader($this->scopedCheck($id), $data, $request->user());

        return response()->json(['success' => true]);
    }

    public function redraw(Request $request, int $id)
    {
        $this->service->redraw($this->scopedCheck($id), $request->user());

        return $this->fresh($id);
    }

    public function submit(SubmitStockCheckRequest $request, int $id)
    {
        $this->service->submit($this->scopedCheck($id), $request->input('channels'), $request->user());

        return $this->fresh($id);
    }

    public function undo(Request $request, int $id)
    {
        $this->service->undo($this->scopedCheck($id), $request->user());

        return $this->fresh($id);
    }

    public function sync(Request $request, int $id)
    {
        $results = $this->service->sync($this->scopedCheck($id), $request->user());

        return $this->fresh($id, [
            'results' => array_map(fn (ChannelSyncResult $r) => $r->toArray(), $results),
        ]);
    }

    public function cancel(Request $request, int $id)
    {
        $this->service->cancel($this->scopedCheck($id), $request->user());

        return $this->fresh($id);
    }

    public function destroy(int $id)
    {
        $check = $this->scopedCheck($id);
        $opsJobId = $check->ops_job_id;

        $this->service->delete($check);

        return response()->json(['success' => true, 'redirect' => '/ops-jobs/'.$opsJobId.'/edit']);
    }

    public function storeAttachment(Request $request, int $id)
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,image/gif,video/mp4,video/quicktime,video/webm,application/pdf',
        ]);

        $check = $this->scopedCheck($id);
        $path = $request->file('file')->storePublicly(self::ATTACHMENT_DIR);

        $check->attachments()->create([
            'full_url' => Storage::url($path),
            'local_url' => $path,
            'name' => mb_substr($request->file('file')->getClientOriginalName(), 0, 255),
        ]);

        return $this->fresh($id);
    }

    public function destroyAttachment(int $id, int $attachmentId)
    {
        $check = $this->scopedCheck($id);
        $attachment = $check->attachments()->findOrFail($attachmentId);

        Storage::delete($attachment->local_url);
        $attachment->delete();

        return $this->fresh($id);
    }

    // -------------------------------------------------------------- internals

    private function scopedCheck(int $id): StockCheck
    {
        $check = StockCheck::with(['opsJob', 'vend', 'channels'])->findOrFail($id);
        $this->assertWithinViewerCeiling($check->opsJob);

        return $check;
    }

    private function loadForEdit(StockCheck $check): StockCheck
    {
        return $check->load([
            'vend', 'customer.deliveryAddress', 'opsJob.deliveredBy:id,name',
            'channels.vendChannel:id,qty,capacity,product_id', 'channels.product:id,code,name', 'channels.product.thumbnail',
            'attachments', 'createdBy:id,name', 'countedBy:id,name', 'syncedBy:id,name',
        ]);
    }

    private function fresh(int $id, array $extra = [])
    {
        return response()->json([
            'success' => true,
            'stockCheck' => new StockCheckResource($this->loadForEdit(StockCheck::findOrFail($id))),
        ] + $extra);
    }

    private function filtered(Request $request): Builder
    {
        $viewerOperatorId = OperatorVendFilterScope::viewerOperatorId();

        return StockCheck::query()
            ->select('stock_checks.*')
            ->join('ops_jobs', 'ops_jobs.id', '=', 'stock_checks.ops_job_id')
            // The ceiling first; request filters below may only narrow it.
            ->when($viewerOperatorId !== null, fn ($q) => $q->where('stock_checks.operator_id', $viewerOperatorId))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('ops_jobs.date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('ops_jobs.date', '<=', $request->date_to))
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('stock_checks.status', (int) $request->status))
            ->when($request->filled('delivered_by') && $request->delivered_by !== 'all', fn ($q) => $q->where('ops_jobs.delivered_by', (int) $request->delivered_by))
            ->when($request->filled('vend_code'), function ($q) use ($request) {
                $vendIds = Vend::withoutGlobalScope(OperatorVendFilterScope::class)->select('id');
                VendCode::whereSearch($vendIds, $request->vend_code);
                $q->whereIn('stock_checks.vend_id', $vendIds);
            })
            ->when($request->filled('customer'), fn ($q) => $q->whereIn('stock_checks.customer_id', function ($sub) use ($request) {
                $sub->select('id')->from('customers')->where('name', 'like', '%'.$request->customer.'%');
            }))
            ->when($request->input('mismatch') === 'true', fn ($q) => $q->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('stock_check_channels')
                    ->whereColumn('stock_check_channels.stock_check_id', 'stock_checks.id')
                    ->where('stock_check_channels.variance_qty', '<>', 0);
            }))
            ->when($request->input('mismatch') === 'short', fn ($q) => $q->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('stock_check_channels')
                    ->whereColumn('stock_check_channels.stock_check_id', 'stock_checks.id')
                    ->where('stock_check_channels.variance_qty', '<', 0);
            }));
    }

    private function sorted(Builder $query, Request $request): Builder
    {
        $key = in_array($request->sortKey, self::SORTABLE, true) ? $request->sortKey : 'job_date';
        $direction = filter_var($request->input('sortBy', false), FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';
        $column = $key === 'job_date' ? 'ops_jobs.date' : 'stock_checks.'.$key;

        return $query->orderBy($column, $direction)->orderBy('stock_checks.id', 'desc');
    }

    private function filterState(Request $request): array
    {
        return [
            'date_from' => $request->input('date_from', ''),
            'date_to' => $request->input('date_to', ''),
            'status' => $request->input('status', 'all'),
            'delivered_by' => $request->input('delivered_by', 'all'),
            'vend_code' => $request->input('vend_code', ''),
            'customer' => $request->input('customer', ''),
            'mismatch' => $request->input('mismatch', 'all'),
            'sortKey' => in_array($request->sortKey, self::SORTABLE, true) ? $request->sortKey : 'job_date',
            'sortBy' => filter_var($request->input('sortBy', false), FILTER_VALIDATE_BOOLEAN),
            'numberPerPage' => $request->input('numberPerPage', 100),
        ];
    }
}
