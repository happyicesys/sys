<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesOpsJobStops;
use App\Http\Requests\ServiceNotice\ServiceNoticeItemStatusRequest;
use App\Http\Requests\ServiceNotice\StoreServiceNoticeAttachmentRequest;
use App\Http\Requests\ServiceNotice\StoreServiceNoticeRequest;
use App\Http\Requests\ServiceNotice\UpdateServiceNoticeItemRequest;
use App\Http\Resources\ServiceNoticeResource;
use App\Models\Attachment;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\ServiceNotice;
use App\Models\ServiceNoticeItem;
use App\Models\Vend;
use App\Services\ServiceNotice\ServiceNoticeService;
use App\Support\VendCode;
use App\Traits\ExportOptimizationTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * Service notices: repair stops inside an ops job. Thin on purpose — the rules
 * are ServiceNoticeService's. Every route is permission-gated here AND held to
 * the operator ceiling of the notice's ops job.
 */
class ServiceNoticeController extends Controller
{
    use ExportOptimizationTrait;
    use ManagesOpsJobStops;

    private const SORTABLE = ['code', 'status', 'created_at', 'completed_at', 'job_date'];

    public function __construct(private ServiceNoticeService $service)
    {
        $this->middleware('auth');
        $this->middleware(['permission:read service-notices'])->only(['index', 'edit']);
        $this->middleware(['permission:export service-notices'])->only(['exportExcel']);
        $this->middleware(['permission:create service-notices'])->only(['store']);
        $this->middleware(['permission:update service-notices'])->only([
            'update', 'updateSequence', 'storeItem', 'updateItem', 'setItemStatus',
            'storeAttachment', 'destroyAttachment', 'complete', 'undoComplete', 'cancel',
        ]);
        $this->middleware(['permission:delete service-notices'])->only(['destroy', 'destroyItem']);
    }

    // ---------------------------------------------------------------- listing

    public function index(Request $request)
    {
        $request->validate(['numberPerPage' => ['nullable', 'regex:/^(\d+|All)$/']]);
        $numberPerPage = $request->numberPerPage ?: 100;

        $notices = $this->sorted($this->filtered($request), $request)
            ->with(['vend', 'customer:id,name', 'items:id,service_notice_id,status', 'opsJob.deliveredBy:id,name', 'createdBy:id,name', 'completedBy:id,name'])
            ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('ServiceNotice/Index', [
            'serviceNotices' => ServiceNoticeResource::collection($notices),
            'statusOptions' => collect(ServiceNotice::STATUS_MAPPINGS)->map(fn ($name, $id) => ['id' => (string) $id, 'value' => $name])->values(),
            'driverOptions' => $this->stopDriverOptions(),
            'filters' => $this->filterState($request),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = $this->sorted($this->filtered($request), $request)
            ->with(['vend', 'customer:id,name', 'items', 'opsJob.deliveredBy:id,name', 'createdBy:id,name', 'completedBy:id,name']);

        // One row per service ITEM: the office chases items, not notices.
        $rows = (function () use ($query) {
            foreach ($query->lazy(200) as $notice) {
                foreach ($notice->items as $item) {
                    yield [
                        'Service Notice' => $notice->display_code,
                        'Job Date' => $notice->opsJob?->date?->format('Y-m-d'),
                        'Assigned To' => $notice->opsJob?->deliveredBy?->name,
                        'Machine ID' => $notice->vend?->codeLabel(),
                        'Site' => $notice->customer?->name,
                        'Notice Status' => $notice->statusName(),
                        'Item #' => $item->sequence,
                        'Description' => $item->desc,
                        'Before' => $item->desc_before,
                        'After' => $item->desc_after,
                        'Item Status' => $item->statusName(),
                        'Incomplete Reason' => $item->incomplete_reason,
                        'Completed At' => $notice->completed_at?->format('Y-m-d H:i'),
                        'Completed By' => $notice->completedBy?->name,
                        'Created By' => $notice->createdBy?->name,
                        'Remarks' => $notice->remarks,
                    ];
                }
            }
        })();

        return (new FastExcel($rows))->download($this->formatExportFilename('ServiceNotices', 'xlsx'));
    }

    // ----------------------------------------------------------------- notice

    public function store(StoreServiceNoticeRequest $request, int $opsJobId)
    {
        $opsJob = $this->scopedOpsJob($opsJobId);
        $vend = $this->vendVisibleToViewer($request->integer('vend_id'));

        $notice = $this->service->create(
            $opsJob,
            $vend,
            $request->itemLines(),
            $request->input('remarks'),
            $request->input('sequence'),
            $request->user(),
        );

        return response()->json(['success' => true, 'id' => $notice->id, 'display_code' => $notice->display_code]);
    }

    public function edit(int $id)
    {
        $notice = $this->scopedNotice($id);

        return Inertia::render('ServiceNotice/Edit', [
            'serviceNotice' => new ServiceNoticeResource($this->loadForEdit($notice)),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['remarks' => 'nullable|string|max:2000']);

        $this->service->updateHeader($this->scopedNotice($id), $data, $request->user());

        return $this->fresh($id);
    }

    public function updateSequence(Request $request, int $id)
    {
        $data = $request->validate(['sequence' => 'nullable|numeric|min:0.1']);

        $this->service->updateHeader($this->scopedNotice($id), $data, $request->user());

        return response()->json(['success' => true]);
    }

    public function complete(Request $request, int $id)
    {
        $this->service->complete($this->scopedNotice($id), $request->user());

        return $this->fresh($id);
    }

    public function undoComplete(Request $request, int $id)
    {
        $this->service->undoComplete($this->scopedNotice($id), $request->user());

        return $this->fresh($id);
    }

    public function cancel(Request $request, int $id)
    {
        $this->service->cancel($this->scopedNotice($id), $request->user());

        return $this->fresh($id);
    }

    public function destroy(int $id)
    {
        $notice = $this->scopedNotice($id);
        $opsJobId = $notice->ops_job_id;

        $this->service->delete($notice);

        return response()->json(['success' => true, 'redirect' => '/ops-jobs/'.$opsJobId.'/edit']);
    }

    // ------------------------------------------------------------------ items

    public function storeItem(Request $request, int $id)
    {
        $data = $request->validate(['desc' => 'required|string|max:2000']);

        $this->service->addItem($this->scopedNotice($id), $data['desc'], $request->user());

        return $this->fresh($id);
    }

    public function updateItem(UpdateServiceNoticeItemRequest $request, int $itemId)
    {
        $item = $this->scopedItem($itemId);

        $this->service->updateItem($item, $request->validated(), $request->user());

        return $this->fresh($item->service_notice_id);
    }

    public function setItemStatus(ServiceNoticeItemStatusRequest $request, int $itemId)
    {
        $item = $this->scopedItem($itemId);

        $this->service->setItemStatus($item, $request->integer('status'), $request->input('incomplete_reason'), $request->user());

        return $this->fresh($item->service_notice_id);
    }

    public function destroyItem(int $itemId)
    {
        $item = $this->scopedItem($itemId);
        $noticeId = $item->service_notice_id;

        $this->service->deleteItem($item);

        return $this->fresh($noticeId);
    }

    public function storeAttachment(StoreServiceNoticeAttachmentRequest $request, int $itemId)
    {
        $item = $this->scopedItem($itemId);

        $this->service->storeAttachment($item, $request->integer('slot'), $request->file('file'));

        return $this->fresh($item->service_notice_id);
    }

    public function destroyAttachment(int $itemId, int $attachmentId)
    {
        $item = $this->scopedItem($itemId);
        // Reached THROUGH the item, so an attachment id of some other model can never be deleted here.
        $attachment = $item->attachments()->findOrFail($attachmentId);

        abort_unless($item->serviceNotice->isPending(), 422, 'Reopen the notice before removing files.');

        $this->service->deleteAttachment($attachment);

        return $this->fresh($item->service_notice_id);
    }

    // -------------------------------------------------------------- internals

    private function scopedNotice(int $id): ServiceNotice
    {
        $notice = ServiceNotice::with('opsJob')->findOrFail($id);
        $this->assertWithinViewerCeiling($notice->opsJob);

        return $notice;
    }

    private function scopedItem(int $itemId): ServiceNoticeItem
    {
        $item = ServiceNoticeItem::with('serviceNotice.opsJob')->findOrFail($itemId);
        $this->assertWithinViewerCeiling($item->serviceNotice->opsJob);

        return $item;
    }

    private function loadForEdit(ServiceNotice $notice): ServiceNotice
    {
        return $notice->load([
            'vend', 'customer.deliveryAddress', 'opsJob.deliveredBy:id,name',
            'items.attachments', 'items.statusChangedBy:id,name',
            'createdBy:id,name', 'completedBy:id,name',
        ]);
    }

    private function fresh(int $id)
    {
        return response()->json([
            'success' => true,
            'serviceNotice' => new ServiceNoticeResource($this->loadForEdit(ServiceNotice::findOrFail($id))),
        ]);
    }

    private function filtered(Request $request): Builder
    {
        $viewerOperatorId = OperatorVendFilterScope::viewerOperatorId();

        return ServiceNotice::query()
            ->select('service_notices.*')
            ->join('ops_jobs', 'ops_jobs.id', '=', 'service_notices.ops_job_id')
            // The ceiling first; request filters below may only narrow it.
            ->when($viewerOperatorId !== null, fn ($q) => $q->where('service_notices.operator_id', $viewerOperatorId))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('ops_jobs.date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('ops_jobs.date', '<=', $request->date_to))
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('service_notices.status', (int) $request->status))
            ->when($request->filled('delivered_by') && $request->delivered_by !== 'all', fn ($q) => $q->where('ops_jobs.delivered_by', (int) $request->delivered_by))
            ->when($request->filled('vend_code'), fn ($q) => $q->whereIn('service_notices.vend_id', $this->vendIdsMatching($request->vend_code)))
            ->when($request->filled('customer'), fn ($q) => $q->whereIn('service_notices.customer_id', function ($sub) use ($request) {
                $sub->select('id')->from('customers')->where('name', 'like', '%'.$request->customer.'%');
            }))
            ->when($request->input('has_incomplete') === 'true', fn ($q) => $q->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('service_notice_items')
                    ->whereColumn('service_notice_items.service_notice_id', 'service_notices.id')
                    ->where('service_notice_items.status', ServiceNoticeItem::STATUS_INCOMPLETE);
            }));
    }

    private function vendIdsMatching(string $search)
    {
        $query = Vend::withoutGlobalScope(OperatorVendFilterScope::class)->select('id');
        VendCode::whereSearch($query, $search);

        return $query;
    }

    private function sorted(Builder $query, Request $request): Builder
    {
        $key = in_array($request->sortKey, self::SORTABLE, true) ? $request->sortKey : 'job_date';
        $direction = filter_var($request->input('sortBy', false), FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';
        $column = $key === 'job_date' ? 'ops_jobs.date' : 'service_notices.'.$key;

        return $query->orderBy($column, $direction)->orderBy('service_notices.id', 'desc');
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
            'has_incomplete' => $request->input('has_incomplete', 'all'),
            'sortKey' => in_array($request->sortKey, self::SORTABLE, true) ? $request->sortKey : 'job_date',
            'sortBy' => filter_var($request->input('sortBy', false), FILTER_VALIDATE_BOOLEAN),
            'numberPerPage' => $request->input('numberPerPage', 100),
        ];
    }
}
