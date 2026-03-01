<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\DeliveryPlatformOperatorResource;
use App\Http\Resources\DeliveryPlatformRefNumberResource;
use App\Http\Resources\DeliveryProductMappingResource;
use App\Http\Resources\DeliveryProductMappingVendResource;
use App\Http\Resources\OperatorResource;
use App\Models\DeliveryProductMappingVend;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryProductMapping;
use App\Models\Operator;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use DB;
use Inertia\Inertia;

class DeliveryProductMappingVendController extends Controller
{
    use GetUserTimezone;

    public function index(Request $request)
    {
        $request->merge([
            'date_from' => $request->date_from ? $request->date_from : Carbon::today()->setTimezone($this->getUserTimezone())->startOfWeek(Carbon::SUNDAY)->toDateString(),
            'date_to' => $request->date_to ? $request->date_to : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay()->toDateString(),
            'delivery_product_mapping_id' => $request->delivery_product_mapping_id ? $request->delivery_product_mapping_id : 'all',
            'delivery_platform_type_id' => $request->delivery_platform_type_id ? $request->delivery_platform_type_id : 'all',
            'is_active' => $request->is_active ? $request->is_active : 'true',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : '100',
            'status' => $request->status ? $request->status : 'all',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
            'sortKey' => $request->sortKey ? $request->sortKey : 'platform_ref_id',
        ]);
        if (!$request->operators) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge([
                    'operators' => array_filter([
                        auth()->user()->operator_id,
                        Operator::where('code', 'HIMD')->first()?->id,
                        Operator::where('code', 'LEA')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'UL-ST')->first()?->id,
                    ])
                ]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }

        $deliveryProductMappingVends = DeliveryProductMappingVend::query()
            ->with([
                'vend:id,code,name,customer_id',
                'vend.customer:id,code,name,person_id,virtual_customer_prefix,virtual_customer_code',
                'deliveryProductMapping:id,operator_id',
                'deliveryProductMappingVendChannels:id,delivery_product_mapping_vend_id,delivery_product_mapping_item_id,vend_channel_id,vend_channel_code,amount,qty,reserved_percent,reserved_qty,is_active',
                'deliveryProductMappingVendChannels.deliveryProductMappingVend:id',
                'deliveryProductMappingVendChannels.vendChannel:id,code,capacity,qty',
                'deliveryProductMappingVendChannels.deliveryProductMappingItem:id,amount,channel_code,sub_category_json,product_id',
                'deliveryProductMappingVendChannels.deliveryProductMappingItem.product:id,code,name',
                'deliveryProductMappingVendChannels.deliveryProductMappingItem.product.thumbnail:id,full_url,attachments.modelable_id,attachments.modelable_type',
            ])
            ->withSum([
                'deliveryPlatformOrders' => function ($query) use ($request) {
                    $query
                        ->filterIndex($request)
                        ->when($request->date_from, function ($query, $search) {
                            $query->where('order_created_at', '>=', $search);
                        })
                        ->when($request->date_to, function ($query, $search) {
                            $query->where('order_created_at', '<=', $search);
                        });
                }
            ], 'subtotal_amount')
            ->withSum([
                'deliveryPlatformOrders' => function ($query) use ($request) {
                    $query
                        ->filterIndex($request)
                        ->when($request->date_from, function ($query, $search) {
                            $query->where('order_created_at', '>=', $search);
                        })
                        ->when($request->date_to, function ($query, $search) {
                            $query->where('order_created_at', '<=', $search);
                        });
                }
            ], 'promo_amount')
            ->withCount([
                'deliveryPlatformOrders' => function ($query) use ($request) {
                    $query
                        ->filterIndex($request)
                        ->when($request->date_from, function ($query, $search) {
                            $query->where('order_created_at', '>=', $search);
                        })
                        ->when($request->date_to, function ($query, $search) {
                            $query->where('order_created_at', '<=', $search);
                        });
                }
            ])
            ->filterIndex($request)
            ->when($request->sortKey, function ($query, $search) use ($request) {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
            })
            ->addSelect(DB::raw('(SELECT COUNT(*) FROM delivery_product_mapping_vend y WHERE delivery_product_mapping_vend.platform_ref_id = y.platform_ref_id) AS binded_times'))
            ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();

        $totals = DeliveryPlatformOrder::query()
            ->filterIndex($request)
            ->whereHas('deliveryProductMappingVend', function ($query) use ($request) {
                $query->filterIndex($request);
            })
            ->when($request->date_from, function ($query, $search) {
                $query->where('order_created_at', '>=', Carbon::parse($search)->setTimezone($this->getUserTimezone())->startOfDay());
            })
            ->when($request->date_to, function ($query, $search) {
                $query->where('order_created_at', '<=', Carbon::parse($search)->setTimezone($this->getUserTimezone())->endOfDay());
            })
            ->select(
                DB::raw('CAST(COALESCE(SUM(subtotal_amount), 0) AS UNSIGNED) as subtotal_amount'),
                DB::raw('CAST(COALESCE(SUM(promo_amount), 0) AS UNSIGNED) as promo_amount'),
                DB::raw('COUNT(*) as count')
            )
            ->first();

        return Inertia::render('DeliveryProductMappingVend/Index', [
            'deliveryProductMappingVends' => DeliveryProductMappingVendResource::collection(
                $deliveryProductMappingVends
            ),
            'deliveryPlatformTypeOptions' => DeliveryPlatformOperator::DELIVERY_PLATFORM_TYPES,
            'deliveryProductMappingOptions' => DeliveryProductMappingResource::collection(
                DeliveryProductMapping::all()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'platformRefNumberOptions' => DeliveryPlatformRefNumberResource::collection(
                DeliveryPlatformRefNumber::query()
                    ->where('status', DeliveryPlatformRefNumber::STATUS_ACTIVE)
                    ->orderBy('ref_number')
                    ->get()
            ),
            'totals' => $totals,
        ]);
    }
}
