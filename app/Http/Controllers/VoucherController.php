<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\VoucherResource;
use App\Models\Operator;
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

    public function create()
    {
        return Inertia::render('Voucher/Form', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
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
                    'code' => 'TESTING',
                    'type' => 'percent',
                    'channels' => ['14', '22', '15'],
                    'date_from' => Carbon::today()->subDays(5)->format('Y-m-d'),
                    'date_to' => Carbon::today()->addDays(5)->format('Y-m-d'),
                    'name' => 'Redeem Channel 14, 22, 15 voucher',
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

        return response([
            'status_code' => 404,
            'message' => 'Voucher not found',
        ], 404);

    }
}
