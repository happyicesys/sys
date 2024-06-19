<?php

namespace App\Http\Controllers;

use App\Http\Resources\CashlessProviderResource;
use App\Http\Resources\CashlessTerminalResource;
use App\Http\Resources\OperatorResource;
use App\Models\CashlessProvider;
use App\Models\CashlessTerminal;
use App\Models\Operator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashlessTerminalController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        return Inertia::render('CashlessTerminal/Index', [
            'cashlessTerminals' => CashlessTerminalResource::collection(
                CashlessTerminal::query()
                    ->with([
                        'cashlessProvider',
                        'operator',
                    ])
                    ->when($request->code, function($query, $search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    })
                    ->when($request->cashless_provider_id, function($query, $search) {
                        $query->where('cashless_provider_id', $search);
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'cashlessProviderOptions' => CashlessProviderResource::collection(
                CashlessProvider::orderBy('name')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required',
            'cashless_provider_id' => 'required',
        ]);

        CashlessTerminal::create($request->all());

        return redirect()->route('cashless-terminals');
    }

    public function update(Request $request, $zoneId)
    {
        $request->validate([
            'code' => 'required',
            'cashless_provider_id' => 'required',
        ]);

        $cashlessTerminal = CashlessTerminal::findOrFail($zoneId);
        $cashlessTerminal->update($request->all());

        return redirect()->route('cashless-terminals');
    }

    public function delete($zoneId)
    {
        $cashlessTerminal = CashlessTerminal::findOrFail($zoneId);
        $cashlessTerminal->delete();

        return redirect()->route('cashless-terminals');
    }
}
