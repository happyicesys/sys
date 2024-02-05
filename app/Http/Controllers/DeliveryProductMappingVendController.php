<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\DeliveryPlatformOperatorResource;
use App\Http\Resources\DeliveryProductMappingResource;
use App\Http\Resources\DeliveryProductMappingVendResource;
use App\Models\DeliveryProductMappingVend;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
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
            'date_from' => $request->date_from ? $request->date_from : Carbon::now()->setTimezone($this->getUserTimezone())->startOfWeek()->format('Y-m-d'),
            'date_to' => $request->date_to ? $request->date_to : Carbon::now()->setTimezone($this->getUserTimezone())->format('Y-m-d'),
            'delivery_product_mapping_id' => $request->delivery_product_mapping_id ? $request->delivery_product_mapping_id : 'all',
            'delivery_platform_operator_id' => $request->delivery_platform_operator_id ? $request->delivery_platform_operator_id : '15',
            'is_active' => $request->is_active ? $request->is_active : 'true',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : '100',
            'status' => $request->status ? $request->status : 'all',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
        ]);

        // dd($request->date_from);

        $deliveryProductMappingVends = DeliveryProductMappingVend::query()
        ->with([
            'vend:id,code,name',
            'vend.latestVendBinding.customer:id,code,name',
            'deliveryProductMapping:id,operator_id',
            'deliveryProductMappingVendChannels:id,delivery_product_mapping_vend_id,delivery_product_mapping_item_id,vend_channel_id,vend_channel_code,amount,qty,reserved_percent,reserved_qty,is_active',
            'deliveryProductMappingVendChannels.deliveryProductMappingVend:id',
            'deliveryProductMappingVendChannels.vendChannel:id,code,capacity,qty',
            'deliveryProductMappingVendChannels.deliveryProductMappingItem:id,amount,channel_code,sub_category_json,product_id',
            'deliveryProductMappingVendChannels.deliveryProductMappingItem.product:id,code,name',
            'deliveryProductMappingVendChannels.deliveryProductMappingItem.product.thumbnail:id,full_url,attachments.modelable_id,attachments.modelable_type',
        ])
        ->withSum(['deliveryPlatformOrders' => function($query) use ($request) {
            $query
                ->when($request->date_from, function($query, $search) {
                    $query->where('created_at', '>=', Carbon::parse($search)->startOfDay());
                })
                ->when($request->date_to, function($query, $search) {
                    $query->where('created_at', '<=', Carbon::parse($search)->endOfDay());
                });
        }], 'subtotal_amount')
        ->withCount(['deliveryPlatformOrders' => function($query) use ($request) {
            $query
                ->when($request->date_from, function($query, $search) {
                    $query->where('created_at', '>=', Carbon::parse($search)->startOfDay());
                })
                ->when($request->date_to, function($query, $search) {
                    $query->where('created_at', '<=', Carbon::parse($search)->endOfDay());
                });
        }])
        ->filterIndex($request)
        ->when($request->sortKey, function($query, $search) use ($request) {
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        })
        ->addSelect(DB::raw('(SELECT COUNT(*) FROM delivery_product_mapping_vend y WHERE delivery_product_mapping_vend.platform_ref_id = y.platform_ref_id) AS binded_times'))
        ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
        ->withQueryString();

        return Inertia::render('DeliveryProductMappingVend/Index', [
            'deliveryProductMappingVends' => DeliveryProductMappingVendResource::collection(
                $deliveryProductMappingVends
            ),
            'deliveryPlatformOperatorOptions' => DeliveryPlatformOperatorResource::collection(
                DeliveryPlatformOperator::with('deliveryPlatform')->get()
            ),
            'deliveryProductMappingOptions' => DeliveryProductMappingResource::collection(
                DeliveryProductMapping::all()
            ),
        ]);
    }
}
