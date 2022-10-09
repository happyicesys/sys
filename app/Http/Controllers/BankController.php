<?php

namespace App\Http\Controllers;

use App\Http\Resources\BankResource;
use App\Models\Bank;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Bank/Index', [
            'banks' => BankResource::collection(
                Bank::query()
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

        Bank::create($request->all());

        return redirect()->route('banks');
    }

    public function update(Request $request, $bankId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $bank = Bank::findOrFail($bankId);
        $bank->update($request->all());

        return redirect()->route('banks');
    }

    public function delete($bankId)
    {
        $bank = Bank::findOrFail($bankId);
        $bank->delete();

        return redirect()->route('banks');
    }
}
