<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryPlatformOrderResource;
use App\Models\DeliveryPlatformOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryPlatformOrderController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'order_created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        return Inertia::render('DeliveryPlatformOrder/Index', [
            'deliveryPlatformOrders' => DeliveryPlatformOrderResource::collection(
                DeliveryPlatformOrder::query()
                    ->with([
                        'deliveryPlatform:id,name,country_id,slug',
                        'deliveryPlatformOrderItems',
                        'deliveryProductMappingVend.vend:id,code,name',
                        'deliveryProductMappingVend.vend.latestVendBinding.customer:id,code,name',
                        'orderItemVendChannels.deliveryProductMappingItem.product:id,code,name,is_active',
                        'orderItemVendChannels.deliveryProductMappingItem.product.thumbnail',
                    ])
                    ->when($request->order_id, function($query, $search) {
                        $query->where('order_id', 'LIKE', "%{$search}%");
                    })
                    ->when($request->short_order_id, function($query, $search) {
                        $query->where('short_order_id', 'LIKE', "%{$search}%");
                    })
                    ->when($request->vend_code, function($query, $search) use ($request) {
                        $query->whereHas('deliveryProductMappingVend.vend', function($query) use ($request) {
                            $query->where('code', 'LIKE', "{$request->vend_code}%");
                        });
                    })
                    ->when($request->date_from, function ($query, $search) {
                        $query->where('order_created_at', '>=', Carbon::parse($search)->startOfDay());
                    })
                    ->when($request->date_to, function ($query, $search) {
                        $query->where('order_created_at', '<=', Carbon::parse($search)->endOfDay());
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function edit($id)
    {
        $deliveryPlatformOrder = DeliveryPlatformOrder::query()
            ->with([
                'deliveryPlatform:id,name,country_id,slug',
                'deliveryPlatformOrderItems',
                'deliveryProductMappingVend.deliveryProductMapping:id,name',
                'deliveryProductMappingVend.vend:id,code,name',
                'deliveryProductMappingVend.vend.latestVendBinding.customer:id,code,name',
                'deliveryPlatformOrderItems.deliveryProductMappingItem.product:id,code,name,is_active',
                'deliveryPlatformOrderItems.deliveryProductMappingItem.product.thumbnail',
                'deliveryPlatformOrderItems.orderItemVendChannels',
                // 'orderItemVendChannels.deliveryProductMappingItem.product:id,code,name,is_active',
                // 'orderItemVendChannels.deliveryProductMappingItem.product.thumbnail',
            ])
            ->findOrFail($id);

        return Inertia::render('DeliveryPlatformOrder/Edit', [
            'deliveryPlatformOrder' => DeliveryPlatformOrderResource::make(
                $deliveryPlatformOrder
            ),
        ]);
    }
}
