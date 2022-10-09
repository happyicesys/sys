<?php

namespace App\Http\Controllers;

use App\Http\Resources\StatusResource;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StatusController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Status/Index', [
            'statuses' => StatusResource::collection(
                Status::query()
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        Status::create($request->all());

        return redirect()->route('statuses');
    }

    public function update(Request $request, $statusId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $status = Status::findOrFail($statusId);
        $status->update($request->all());

        return redirect()->route('statuses');
    }

    public function delete($statusId)
    {
        $status = Status::findOrFail($statusId);
        $status->delete();

        return redirect()->route('statuses');
    }
}
