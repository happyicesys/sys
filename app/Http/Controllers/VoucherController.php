<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\VoucherResource;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('Voucher/Index', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vouchers' => VoucherResource::collection(
                Voucher::query()
                    ->with('vends')
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function create($batchType)
    {
        $isUnique = $batchType == 'unique' ? true : false;

        return Inertia::render('Voucher/Form', [
            'isUnique' => $isUnique,
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productOptions' => ProductResource::collection(
                Product::orderBy('code')->where('is_inventory', true)->where('is_active', true)->get()
            ),
            'typeOptions' => Voucher::TYPE_MAPPINGS,
        ]);
    }


    public function search(Request $request)
    {
        $code = $request->code;

        if(!$code) {
            abort(response([
                'status_code' => 400,
                'message' => 'Parameters missing',
            ], 400));
        }

        if($code == 'test') {
            return response([
                'status_code' => 200,
                'message' => 'Voucher successfully reedeemed',
                'voucher' =>
                [
                    'id' => 20,
                    'code' => 'test',
                    'type' => 'percent',
                    'channels' => ['14', '22', '15'],
                    'date_from' => Carbon::today()->subDays(5)->format('Y-m-d'),
                    'date_to' => Carbon::today()->addDays(5)->format('Y-m-d'),
                    'name' => '10% discount on Item 14,15,and 22.',
                    'desc' => '',
                    'status' => 'active',
                    'min_value' => 200,
                    'max_promo_value' => 1000,
                    'qty' => 1,
                    'value' => 10,
                    'matrix' => []
                ],
            ], 200);
        }

        if($code == 'test20') {
            return response([
                'status_code' => 200,
                'message' => 'Voucher successfully reedeemed',
                'voucher' =>
                [
                    'id' => 30,
                    'code' => 'test20',
                    'type' => 'percent',
                    'channels' => ['14', '22', '15'],
                    'date_from' => Carbon::today()->subDays(5)->format('Y-m-d'),
                    'date_to' => Carbon::today()->addDays(5)->format('Y-m-d'),
                    'name' => '20% discount on Item 14,15,and 22.',
                    'desc' => '',
                    'status' => 'active',
                    'min_value' => 200,
                    'max_promo_value' => 2000,
                    'qty' => 1,
                    'value' => 20,
                    'matrix' => []
                ],
            ], 200);
        }

        if($code == 'freecornetto') {
            return response([
                'status_code' => 200,
                'message' => 'Voucher successfully reedeemed',
                'voucher' =>
                [
                    'id' => 30,
                    'code' => 'freecornetto',
                    'type' => 'item',
                    'channels' => ['14', '15', '16', '22'],
                    'date_from' => Carbon::today()->subDays(5)->format('Y-m-d'),
                    'date_to' => Carbon::today()->addDays(1)->format('Y-m-d'),
                    'name' => 'Free Cornetto with order over $10',
                    'desc' => '',
                    'status' => 'active',
                    'min_value' => 1000,
                    'max_promo_value' => '',
                    'qty' => 1,
                    'value' => '',
                    'matrix' => []
                ],
            ], 200);
        }

        return response([
            'status_code' => 404,
            'message' => 'Voucher not found',
        ], 404);
    }

    public function store(Request $request)
    {

        dd($request->all());
        $request->validate([
            'code' => 'nullable|string|max:255',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
            'desc' => 'nullable|string|max:255',
            'is_batch_code' => 'boolean',
            'max_promo_value' => 'nullable|numeric',
            'max_redemption_count' => 'nullable|integer',
            'min_value' => 'nullable|numeric',
            'name' => 'required|string|max:255',
            'product_json' => 'nullable',
            'qty' => 'required|integer|min:1',
            'response_json' => 'nullable|json',
            'type' => 'required|string|max:255',
            'value' => 'nullable|numeric|min:0',
        ]);

        $voucher = Voucher::create($request->all());

        return response([
            'status_code' => 201,
            'message' => __('Voucher created successfully'),
            'voucher' => new VoucherResource($voucher),
        ], 201);
    }
}
