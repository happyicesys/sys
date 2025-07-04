<?php

namespace App\Http\Controllers;

use App\Http\Resources\HidCardResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendResource;
use App\Models\HidCard;
use App\Models\Operator;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

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
                        if($search !== 'all') {
                            $query->where('operator_id', $search);
                        }
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
        return Inertia::render('HidCard/Create', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendOptions' => VendResource::collection(
                    Vend::with([
                    'customer:id,code,name,person_id,virtual_customer_code,virtual_customer_prefix,is_active,operator_id',
                ])
                ->when($request->operator_id, function($query, $search) {
                    $query->where('operator_id', $search);
                })
                ->has('customer')
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
            ),
        ]);
    }

    public function edit(Request $request, $hidCardID)
    {
        $hidCard = HidCard::with(['operator', 'vends'])->findOrFail($hidCardID);
        $request->merge(['operator_id' => $hidCard->operator_id]);

        // dd($hidCard->operator_id, $request->operator_id);

        return Inertia::render('HidCard/Edit', [
            'hidCard' => new HidCardResource($hidCard),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendOptions' => VendResource::collection(
                    Vend::with([
                    'customer:id,code,name,person_id,virtual_customer_code,virtual_customer_prefix,is_active,operator_id',
                ])
                ->when($request->operator_id, function($query, $search) {
                    $query->where('operator_id', $search);
                })
                ->has('customer')
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
            ),
        ]);
    }


    public function delete($hidCardID)
    {
        $hidCard = HidCard::findOrFail($hidCardID);

        if($hidCard->vends()->count() > 0) {
            $hidCard->vends()->detach();
        }

        $hidCard->delete();

        return redirect()->route('hid-cards');
    }

    public function exportExcel(Request $request)
    {
        $request->merge([
            'sortKey' => $request->sortKey ? $request->sortKey : 'value',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);
        // dd($request->all());

        $hidCards = HidCard::query()
            ->with('operator')
            ->when($request->value, function($query, $search) {
                $query->where('value', 'LIKE', "{$search}%");
            })
            ->when($request->operator_id, function($query, $search) {
                if($search !== 'all') {
                    $query->where('operator_id', $search);
                }
            })
            ->when($request->sortKey, function($query, $search) use ($request) {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            })
            ->get();

            return (new FastExcel($this->yieldOneByOne($hidCards)))->download('HIDCards'.Carbon::now()->toDateTimeString().'.xlsx', function ($hidCard) {
                return [
                    'Card Value' => $hidCard->value,
                    'Operator' => $hidCard->operator?->code,
                    'Name' => $hidCard->name,
                    'Email' => $hidCard->email,
                ];
            });
    }


    public function search(Request $request)
    {
        $hidCardValue = $request->hid_card_id;
        $vendCode = $request->vend_code;

        $vend = Vend::where('code', $vendCode)->first();

        if (!$vend) {
            return response()->json([
                'status_code' => 404,
                'message' => 'Vend not found',
            ], 404);
        }

        $hidCard = HidCard::where('value', $hidCardValue)
            ->where('operator_id', $vend->operator_id)
            ->with('vends') // eager load vends for checking
            ->first();

        if (!$hidCard) {
            return response()->json([
                'status_code' => 404,
                'message' => 'Sorry, invalid HID card',
            ], 404);
        }

        // Check if the HID card has specific vends bound to it
        if ($hidCard->vends->isNotEmpty()) {
            $isVendAllowed = $hidCard->vends->contains('id', $vend->id);
            if (!$isVendAllowed) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'This HID card is not allowed for this vend',
                ], 403);
            }
        }

        return response()->json([
            'status_code' => 200,
            'message' => 'HID card found',
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'value' => 'required',
            'operator_id' => 'required',
        ]);

        $checkReplication = HidCard::where('value', $request->value)->where('operator_id', $request->operator_id)->first();

        if($checkReplication) {
            return redirect()->back()->withErrors(['value' => 'HID Card already exists for this operator.']);
        }

        $hidCard = HidCard::create([
            'value' => $request->value,
            'operator_id' => $request->operator_id,
            'email' => $request->email,
            'name' => $request->name,
        ]);

        $hidCard->vends()->sync($request->vends);

        return redirect()->route('hid-cards')->with('success', 'HID Card created.');
    }

    public function update(Request $request, $hidCardID)
    {
        $request->validate([
            'value' => 'required',
            'operator_id' => 'required',
        ]);
        // dd($request->all());

        $hidCard = HidCard::findOrFail($hidCardID);

        if($hidCard->value != $request->value) {
            $checkReplication = HidCard::where('value', $request->value)->where('operator_id', $request->operator_id)->first();

            if($checkReplication) {
                return redirect()->back()->withErrors(['value' => 'HID Card already exists for this operator.']);
            }
        }

        $hidCard->update($request->all());

        $hidCard->vends()->sync($request->vends);

        return redirect()->route('hid-cards.edit', ['id' => $hidCard->id])->with('success', 'Voucher updated successfully');
    }

    private function yieldOneByOne($items) {
        foreach($items as $item) {
            yield $item;
        }
    }

}
