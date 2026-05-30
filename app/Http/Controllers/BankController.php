<?php

namespace App\Http\Controllers;

use App\Http\Resources\BankResource;
use App\Models\Bank;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Bank/Index', [
            'banks' => BankResource::collection(
                Bank::query()
                    ->with('country')
                    ->when($request->name, function ($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->sortKey, function ($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
            'countries' => $optionsService->countries(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'country_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        Bank::create([
            'name' => $request->name,
            'country_id' => $request->country_id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('banks');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'country_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $bank = Bank::findOrFail($id);
        $bank->update([
            'name' => $request->name,
            'country_id' => $request->country_id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('banks');
    }

    public function delete($id)
    {
        $bank = Bank::findOrFail($id);
        $bank->delete();

        return redirect()->route('banks');
    }
}
