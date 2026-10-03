<?php

namespace App\Http\Controllers;

use App\Http\Resources\CardTerminalResource;
use App\Models\CardTerminal;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CardTerminalController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('CardTerminal/Index', [
            'cardTerminals' => CardTerminalResource::collection(
                CardTerminal::query()
                    ->when($request->name, function ($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function ($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'remarks' => 'nullable|string',
            'can_bind_multiple_vends' => 'boolean',
        ]);

        CardTerminal::create($data);

        return redirect()->route('card-terminals');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required',
            'remarks' => 'nullable|string',
            'can_bind_multiple_vends' => 'boolean',
        ]);

        $cardTerminal = CardTerminal::findOrFail($id);
        $cardTerminal->update($data);

        return redirect()->route('card-terminals');
    }

    public function delete($id)
    {
        $cardTerminal = CardTerminal::findOrFail($id);
        $cardTerminal->delete();

        return redirect()->route('card-terminals');
    }
}
