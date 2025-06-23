<?php

namespace App\Http\Controllers;

use App\Http\Resources\HidCardResource;
use App\Http\Resources\OperatorResource;
use App\Models\HidCard;
use App\Models\Operator;
use App\Models\Vend;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HidCardController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        return Inertia::render('HidCard/Index', [
            'hidCards' => HidCardResource::collection(
                HidCard::with('operator')
                    ->when($request->value, function($query, $search) {
                        $query->where('value', 'LIKE', "{$search}%");
                    })
                    ->when($request->operator_id, function($query, $search) {
                        $query->where('operator_id', $search);
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'value' => 'required',
            'operator_id' => 'required',
        ]);

        HidCard::create($request->all());

        return redirect()->route('hid-cards');
    }

    public function delete($hidCardID)
    {
        $hidCard = HidCard::findOrFail($hidCardID);
        $hidCard->delete();

        return redirect()->route('hid-cards');
    }

    public function search(Request $request)
    {
        $hidCardValue = $request->hid_card_id;
        $vendCode = $request->vend_code;
        $vend = Vend::where('code', $vendCode)->first();

        if(!$vend) {
            return response()->json([
                'status_code' => 404,
                'message' => 'Vend not found'
            ], 404);
        }

        $hidCard = HidCard::where('value', $hidCardValue)
            ->where('operator_id', $vend->operator_id)
            ->first();

        if (!$hidCard) {
            return response()->json([
                'status_code' => 404,
                'message' => 'HID card not found'
            ], 404);
        }

        return response()->json([
            'status_code' => 200,
            'message' => 'HID card found',
        ], 200);


        return HidCardResource::collection($hidCards);
    }

    public function update(Request $request, $hidCardID)
    {
        $request->validate([
            'value' => 'required',
            'operator_id' => 'required',
        ]);

        $hidCard = HidCard::findOrFail($hidCardID);
        $hidCard->update($request->all());

        return redirect()->route('hid-cards');
    }

}
