<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\VoucherResource;
use App\Http\Resources\VoucherApiResource;
use App\Http\Resources\VoucherCheckingApiResource;
use App\Http\Resources\VoucherItemApiResource;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Vend;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

class VoucherController extends Controller
{
    protected $voucherService;

    public function __construct()
    {
        $this->voucherService = new VoucherService();
    }

    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
        ]);

        return Inertia::render('Voucher/Index', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vouchers' => VoucherResource::collection(
                Voucher::query()
                    ->with('voucherItems')
                    ->filterIndex($request)
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function create($batchType)
    {
        $isUnique = $batchType == 'unique' ? true : false;

        return Inertia::render('Voucher/Create', [
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
            'dcvendMemberTypeMappings' => Voucher::DCVEND_MEMBER_TYPE_MAPPINGS,
            'voucherModeMappings' => Voucher::VOUCHER_MODE_MAPPINGS,
            'voucherPlatformMappings' => Voucher::VOUCHER_PLATFORM_MAPPINGS,
        ]);
    }

    public function edit($id)
    {
        $voucher = Voucher::with('voucherItems')->find($id);

        return Inertia::render('Voucher/Edit', [
            'isUnique' => $voucher->is_batch_code ? false : true,
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productOptions' => ProductResource::collection(
                Product::orderBy('code')->where('is_inventory', true)->where('is_active', true)->get()
            ),
            'typeOptions' => Voucher::TYPE_MAPPINGS,
            'voucher' => VoucherResource::make($voucher),
        ]);
    }

    public function exportExcelVoucherCodes(Request $request)
    {
        $voucher = Voucher::with('voucherItems')->find($request->id);

        if($voucher->voucherItems()->exists()) {
            return (new FastExcel($this->yieldOneByOne($voucher->voucherItems)))->download('Voucher_Codes_'.Carbon::now()->toDateTimeString().'.xlsx', function ($voucherItem) {
                return [
                    'Code' => $voucherItem->code,
                    'Status' => Voucher::STATUS_MAPPINGS[$voucherItem->status],
                    'Redeemed At' => $voucherItem->redeemed_at ? Carbon::parse($voucherItem->redeemed_at)->toDateTimeString() : '',
                ];
            });
        }
    }


    public function search(Request $request)
    {
        $code = $request->code;
        $codeArr = [];
        $dcvendUserID = $request->dcvend_user_id ?? null;
        $vendCode = $request->vend_code;
        $vend = Vend::where('code', $vendCode)->first();

        if (!$code) {
            return response([
                'status_code' => 400,
                'message' => 'Parameters missing',
            ], 400);
        }

        if (is_array($code)) {
            $codeArr = $code;
        } else {
            if ($code == 'test') {
                return response([
                    'status_code' => 200,
                    'message' => 'Voucher successfully reedeemed',
                    'voucher' => [
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

            if ($code == 'test20') {
                return response([
                    'status_code' => 200,
                    'message' => 'Voucher successfully reedeemed',
                    'voucher' => [
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

            if ($code == 'freecornetto') {
                return response([
                    'status_code' => 200,
                    'message' => 'Voucher successfully reedeemed',
                    'voucher' => [
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
                        'max_promo_value' => null,
                        'qty' => 1,
                        'value' => null,
                        'matrix' => []
                    ],
                ], 200);
            }

            if ($code == 'discount2d') {
                return response([
                    'status_code' => 200,
                    'message' => 'Voucher successfully reedeemed',
                    'voucher' => [
                        'id' => 30,
                        'code' => 'discount2d',
                        'type' => 'amount',
                        'channels' => [],
                        'date_from' => Carbon::today()->subDays(5)->format('Y-m-d'),
                        'date_to' => Carbon::today()->addDays(1)->format('Y-m-d'),
                        'name' => 'Discount 2 Dollar with purchase more than 10',
                        'desc' => '',
                        'status' => 'active',
                        'min_value' => 1000,
                        'max_promo_value' => null,
                        'qty' => 1,
                        'value' => null,
                        'matrix' => []
                    ],
                ], 200);
            }

            $codeArr = [$code];
        }

        if (count($codeArr) === 1) {
            $isSameCode = false;

            $voucher = Voucher::with('voucherItems')->whereIn('code', $codeArr)->first();

            if (!$voucher) {
                $voucher = VoucherItem::with('voucher')->whereIn('code', $codeArr)->first();
                $isSameCode = false;
            } else {
                $isSameCode = true;
            }

            if (!$voucher) {
                return response([
                    'status_code' => 400,
                    'message' => 'Voucher not found',
                ], 400);
            }

            $resource = new VoucherCheckingApiResource($voucher, $vendCode, $dcvendUserID);
            $data = $resource->toArray($request);

            if (empty($data['channels'])) {
                return response([
                    'status_code' => 400,
                    'message' => 'Product not available for this machine, please try at other machine',
                ], 400);
            }

            $status = $voucher->status ?? ($voucher->voucher->status ?? null);

            if ($status == Voucher::STATUS_ACTIVE) {
                return response([
                    'status_code' => 200,
                    'message' => 'Voucher successfully reedeemed',
                    'voucher' => $data,
                ], 200);
            }

            if ($status == Voucher::STATUS_REDEEMED) {
                return response([
                    'status_code' => 400,
                    'message' => 'Voucher already redeemed',
                    'voucher' => $data,
                ], 400);
            }

            if ($status == Voucher::STATUS_EXPIRED) {
                return response([
                    'status_code' => 400,
                    'message' => 'Voucher expired',
                    'voucher' => $data,
                ], 400);
            }
        } else {
            $vouchers = Voucher::with('voucherItems')->whereIn('code', $codeArr)->get();
            $voucherItems = VoucherItem::with('voucher')->whereIn('code', $codeArr)->get();

            $merged = collect()->merge($vouchers)->merge($voucherItems);

            $resources = $merged->map(function ($voucher) use ($vendCode, $dcvendUserID, $request) {
                $resource = new VoucherCheckingApiResource($voucher, $vendCode, $dcvendUserID);
                return $resource->toArray($request);
            });

            $hasInvalid = $resources->contains(function ($voucher) {
                return in_array($voucher['status'], ['redeemed', 'expired']);
            });

            $hasEmptyChannels = $resources->contains(function ($voucher) {
                return empty($voucher['channels']);
            });

            if ($hasEmptyChannels) {
                return response([
                    'status_code' => 400,
                    'message' => 'Some products not available for this machine, please try at other machine',
                ], 400);
            }

            if ($hasInvalid) {
                return response([
                    'status_code' => 400,
                    'message' => 'Voucher already expired or redeemed',
                    'vouchers' => $resources,
                ], 400);
            }

            if ($resources->isNotEmpty()) {
                return response([
                    'status_code' => 200,
                    'message' => 'Vouchers successfully reedeemed',
                    'vouchers' => $resources,
                ], 200);
            }
        }
    }


    public function store(Request $request)
    {
        $request->merge([
            'product_json' => $request->products ? $request->products : null,
        ]);

        // dd($request->all());

        if($request->is_batch_code) {
            $validatedRequest = $request->validate([
                'code' => 'nullable|string|max:255',
                'date_from' => 'required|date',
                'date_to' => 'required|date',
                'desc' => 'nullable|string|max:255',
                'is_batch_code' => 'boolean',
                'max_promo_value' => 'nullable|numeric',
                'max_redemption_count' => 'nullable|integer',
                'min_value' => 'nullable|numeric',
                'name' => 'required|string|max:255',
                'operator_id' => 'nullable',
                'product_json' => 'nullable',
                'qty' => 'required|integer|min:1',
                'response_json' => 'nullable|json',
                'type' => 'required|string|max:255',
                'value' => 'nullable|numeric|min:0',
            ]);
        }else {
            $validatedRequest = $request->validate([
                'code' => 'required|string|max:255|unique:App\Models\Voucher,code',
                'date_from' => 'required|date',
                'date_to' => 'required|date',
                'desc' => 'nullable|string|max:255',
                'is_batch_code' => 'boolean',
                'max_promo_value' => 'nullable|numeric',
                'max_redemption_count' => 'nullable|integer',
                'min_value' => 'nullable|numeric',
                'name' => 'required|string|max:255',
                'operator_id' => 'nullable',
                'product_json' => 'nullable',
                'qty' => 'required|integer|min:1',
                'response_json' => 'nullable|json',
                'type' => 'required|string|max:255',
                'value' => 'nullable|numeric|min:0',
            ]);
        }


        $voucher = Voucher::create($validatedRequest);

        $this->voucherService->syncVoucherItems($voucher);

        return redirect()->route('vouchers');
    }

    public function update(Request $request, $id)
    {
        $voucher = Voucher::find($id);

        if (!$voucher) {
            return response([
                'status_code' => 404,
                'message' => 'Voucher not found',
            ], 404);
        }

        $validatedRequest = $request->validate([
            'code' => 'required',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
            'desc' => 'nullable|string|max:255',
            'is_batch_code' => 'boolean',
            'max_promo_value' => 'nullable|numeric',
            'max_redemption_count' => 'nullable|integer',
            'min_value' => 'nullable|numeric',
            'name' => 'required|string|max:255',
            'operator_id' => 'nullable',
            'product_json' => 'nullable',
            'qty' => 'required|integer|min:1',
            'response_json' => 'nullable|json',
            'type' => 'required|string|max:255',
            'value' => 'nullable|numeric|min:0',
        ]);

        $voucher->update($validatedRequest);

        // $this->voucherService->syncVoucherItems($voucher);

        return redirect()->route('vouchers');
    }

    private function yieldOneByOne($items) {
        foreach($items as $item) {
            yield $item;
        }
    }
}
