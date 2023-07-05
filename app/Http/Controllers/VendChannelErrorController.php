<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendChannelErrorResource;
use App\Models\VendChannelError;
use App\Traits\HasWeightage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendChannelErrorController extends Controller
{
    use HasWeightage;

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'code';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('VendChannelError/Index', [
            'vendChannelErrors' => VendChannelErrorResource::collection(
                VendChannelError::query()
                    ->when($request->code, function($query, $search) {
                        $query->where('code', 'LIKE', "{$search}%");
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
            'code' => 'required',
        ]);

        VendChannelError::create($request->all());

        return redirect()->route('vend-channel-errors');
    }

    public function update(Request $request, $vendChannelErrorId)
    {
        $request->validate([
            'code' => 'required',
        ]);

        $vendChannelError = VendChannelError::findOrFail($vendChannelErrorId);
        $vendChannelError->update($request->all());

        $this->recalculateAllWeightage(get_class($vendChannelError));

        return redirect()->route('vend-channel-errors');
    }

    public function delete($vendChannelErrorId)
    {
        $vendChannelError = VendChannelError::findOrFail($vendChannelErrorId);
        $vendChannelError->delete();

        return redirect()->route('vend-channel-errors');
    }
}
