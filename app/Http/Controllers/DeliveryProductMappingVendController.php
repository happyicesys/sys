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
use Inertia\Inertia;

class DeliveryProductMappingVendController extends Controller
{

    public function index(Request $request)
    {
        $request->merge([
            'delivery_product_mapping_id' => $request->delivery_product_mapping_id ? $request->delivery_product_mapping_id : 'all',
            'delivery_platform_operator_id' => $request->delivery_platform_operator_id ? $request->delivery_platform_operator_id : 'all',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : '100',
            'status' => $request->status ? $request->status : 'all',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
        ]);

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
        ->filterIndex($request)
        ->when($request->sortKey, function($query, $search) use ($request) {
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        })
        ->select(
            'id',
            'delivery_product_mapping_id',
            'delivery_product_mapping_vend_channels_json',
            'end_date',
            'is_active',
            'platform_ref_id',
            'start_date',
            'vend_code',
            'vend_id',
        )
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
