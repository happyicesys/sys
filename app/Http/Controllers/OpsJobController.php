<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\OpsJobResource;
use App\Http\Resources\OpsJobItemResource;
use App\Http\Resources\UserResource;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\User;
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
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay(),
        ]);

        return Inertia::render('OpsJob/Index', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'opsJobs' => OpsJobResource::collection(
                OpsJob::with(['createdBy', 'deliveredBy', 'operator', 'pickedBy', 'updatedBy'])
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
                    ->withQueryString()
            ),
            'userOptions' => UserResource::collection(
                User::orderBy('name')->get()
            ),
        ]);
    }

    public function edit(Request $request, $id)
    {
        return Inertia::render('OpsJob/Edit', [
            'opsJob' => new OpsJobResource(
                OpsJob::with(['createdBy', 'deliveredBy', 'operator', 'pickedBy', 'updatedBy'])
                    ->findOrFail($id)
            ),
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
}
