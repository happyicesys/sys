<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\OpsJobResource;
use App\Http\Resources\OpsJobItemResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendResource;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\User;
use App\Models\Vend;
use App\Traits\GetUserTimezone;
use App\Services\RunningNumberService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpsJobController extends Controller
{
    use GetUserTimezone;

    public function __construct()
    {
        $this->middleware('auth');
        $this->runningNumberService = new RunningNumberService();
    }

    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'date',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay(),
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->addWeek()->setTimezone($this->getUserTimezone())->endOfDay(),
        ]);

        $opsJobs = OpsJob::query()
            ->with(['createdBy', 'deliveredBy', 'operator', 'pickedBy', 'updatedBy'])
            ->withCount('opsJobItems')
            ->when($request->code, function($query, $search) {
                $query->where('code', 'LIKE', "%{$search}%");
            })
            ->when($request->date_from, function($query, $search) {
                $query->where('date', '>=', $search);
            })
            ->when($request->date_to, function($query, $search) {
                $query->where('date', '<=', $search);
            })
            ->when($request->delivered_by, function($query, $search) {
                $query->where('delivered_by', $search);
            })
            ->when($request->created_by, function($query, $search) {
                $query->where('created_by', $search);
            })
            ->when($request->sortKey, function($query, $search) use ($request) {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            })
            ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();

        return Inertia::render('OpsJob/Index', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'opsJobs' => OpsJobResource::collection(
                $opsJobs
            ),
            'userOptions' => UserResource::collection(
                User::orderBy('name')->get()
            ),
        ]);
    }

    public function createCmsEmptyInvoices($id)
    {
        $opsJob = OpsJob::findOrFail($id);

        foreach($opsJob->opsJobItems as $opsJobItem) {
            $this->customerService->createCMSEmptyInvoice($opsJobItem->customer, $opsJob->date, $opsJob->deliveredBy);
        }

        return redirect()->back();
    }

    public function edit(Request $request, $id)
    {
        $opsJob = OpsJob::query()
        ->with([
            'createdBy:id,name', // Select only necessary columns
            'deliveredBy:id,name',
            'operator:id,name',
            'opsJobItems:id,ops_job_id,vend_id', // Select necessary columns
            'opsJobItems.vend:id,customer_id,code',
            'opsJobItems.vend.customer:id,name,person_id,virtual_customer_prefix,virtual_customer_code',
            'pickedBy:id,name',
            'updatedBy:id,name'
        ])
        ->findOrFail($id);

        $unbindedVendOptions = Vend::query()
            ->select(['id', 'customer_id', 'operator_id', 'code']) // Select necessary columns
            ->with(['customer:id,name']) // Select necessary columns
            ->has('customer')
            ->where('operator_id', $opsJob->operator_id)
            ->whereDoesntHave('opsJobItems', function($query) use ($opsJob) {
                $query->where('ops_job_id', $opsJob->id);
            })
            ->get();

        return Inertia::render('OpsJob/Edit', [
            'opsJob' => new OpsJobResource($opsJob),
            'unbindedVendOptions' => VendResource::collection($unbindedVendOptions),
        ]);
    }

    public function assign(Request $request)
    {
        $vendsID = $request->vends_id;
        $driverID = $request->driver_id;
        $date = $request->date;

        $opsJob = OpsJob::updateOrCreate([
            'date' => $date,
            'delivered_by' => $driverID,
        ], [
            'code' => $this->runningNumberService->getRunningCode(new OpsJob()),
            'created_by' => auth()->id(),
            'operator_id' => auth()->user()->operator_id,
            'updated_by' => auth()->id(),
            'updated_at' => null,
        ]);

        foreach($vendsID as $vendID) {
            OpsJobItem::updateOrCreate([
                'ops_job_id' => $opsJob->id,
                'vend_id' => $vendID,
            ],[
                'status' => '1',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }
    }

    public function createItem(Request $request, $id)
    {
        $opsJob = OpsJob::findOrFail($id);

        OpsJobItem::updateOrCreate([
            'ops_job_id' => $opsJob->id,
            'vend_id' => $request->vend_id,
        ],[
            'status' => '1',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required',
            'delivered_by' => 'required',
            'operator_id' => 'required',
        ]);

        $opsJob = OpsJob::create([
            'code' => $this->runningNumberService->getRunningCode(new OpsJob()),
            'created_by' => auth()->id(),
            'date' => $request->date,
            'delivered_by' => $request->delivered_by,
            'operator_id' => $request->operator_id,
            'updated_by' => null,
            'updated_at' => null,
        ]);

        return redirect()->route('ops-jobs');
    }

    public function delete($id)
    {
        OpsJob::findOrFail($id)->delete();

        return redirect()->route('ops-jobs');
    }

    public function deleteItem($id)
    {
        OpsJobItem::findOrFail($id)->delete();

        return redirect()->back();
    }
}
