<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryPlatformOperatorResource;
use App\Http\Resources\DeliveryPlatformOrderResource;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatformOrderItem;
use App\Services\DeliveryPlatformService;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

class DeliveryPlatformOrderController extends Controller
{
    use GetUserTimezone;

    protected $deliveryPlatformService;

    public function __construct(DeliveryPlatformService $deliveryPlatformService)
    {
        $this->deliveryPlatformService = new DeliveryPlatformService();
    }


    public function index(Request $request)
    {
        $request->merge([
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay(),
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay(),
            'delivery_platform_operator_id' => $request->delivery_platform_operator_id ? $request->delivery_platform_operator_id : 'all',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : '100',
            'status' => $request->status ? $request->status : 'all',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
        ]);

        $query = $this->getDeliveryPlatformOrderQuery($request);

        return Inertia::render('DeliveryPlatformOrder/Index', [
            'deliveryPlatformOperatorOptions' => DeliveryPlatformOperatorResource::collection(
                DeliveryPlatformOperator::with('deliveryPlatform')->get()
            ),
            'deliveryPlatformOrders' => DeliveryPlatformOrderResource::collection(
                    $query
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
            'deliveryPlatformOrderStatusOptions' => [
                [
                    'id' => 'all',
                    'name' => 'All',
                ],
                ...collect(DeliveryPlatformOrder::STATUS_MAPPING)->map(function($status, $index) {
                    return [
                        'id' => $index,
                        'name' => $status,
                    ];
                })
            ],
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

    public function exportExcel(Request $request)
    {
        $request->merge([
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay(),
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay(),
            'delivery_platform_operator_id' => $request->delivery_platform_operator_id ? $request->delivery_platform_operator_id : 'all',
            'status' => $request->status ? $request->status : 'all',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
        ]);

        $query = DeliveryPlatformOrderItem::query()
        ->with([
            'deliveryPlatformOrder.deliveryPlatform:id,name,country_id,slug',
            'deliveryProductMappingItem.deliveryProductMapping:id,name',
            'deliveryPlatformOrder.deliveryProductMappingVend.vend:id,code,name',
            'deliveryPlatformOrder.deliveryProductMappingVend.vend.latestVendBinding.customer:id,code,name',
            'deliveryPlatformOrder.deliveryPlatformOperator',
            'deliveryPlatformOrder.deliveryPlatformOrderComplaint',
            'deliveryProductMappingItem.product:id,code,name,is_active',
            'deliveryProductMappingItem.product.thumbnail',
            'orderItemVendChannels',

        ])
        ->when($request->delivery_platform_operator_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('deliveryPlatformOrder', function($query) use ($search) {
                    $query->where('delivery_platform_operator_id', $search);
                });
            }
        })
        ->when($request->order_id, function($query, $search) {
            $query->whereHas('deliveryPlatformOrder', function($query) use ($search) {
                $query->where('order_id', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->short_order_id, function($query, $search) {
            $query->whereHas('deliveryPlatformOrder', function($query) use ($search) {
                $query->where('short_order_id', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->vend_code, function($query, $search) use ($request) {
            $query->whereHas('deliveryPlatformOrder.deliveryProductMappingVend.vend', function($query) use ($request) {
                $query->where('code', 'LIKE', "{$request->vend_code}%");
            });
        })
        ->when($request->date_from, function ($query, $search) {
            $query->whereHas('deliveryPlatformOrder', function($query) use ($search) {
                $query->where('order_created_at', '>=', Carbon::parse($search)->startOfDay());
            });
        })
        ->when($request->date_to, function ($query, $search) {
            $query->whereHas('deliveryPlatformOrder', function($query) use ($search) {
                $query->where('order_created_at', '<=', Carbon::parse($search)->endOfDay());
            });
        })
        ->when($request->status, function ($query, $search) {
            if($search != 'all') {
                $query->whereHas('deliveryPlatformOrder', function($query) use ($search) {
                    $query->where('status', $search);
                });
            }
        })
        ->when($request->has_complaint, function ($query, $search) {
            if($search != 'all') {
                if($search == true) {
                    $query->has('deliveryPlatformOrder.deliveryPlatformOrderComplaint');
                }else {
                    $query->doesntHave('deliveryPlatformOrder.deliveryPlatformOrderComplaint');
                }
            }
        })
        ->when($request->sortKey, function($query, $search) use ($request) {
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        });

        return (new FastExcel($this->yieldOneByOne($query->get())))->download('Delivery_Platform_Order_'.Carbon::now()->toDateTimeString().'.xlsx', function ($orderItem) {
            return [
                'Platform Order ID' => $orderItem->deliveryPlatformOrder->order_id,
                'Short Order ID' => $orderItem->deliveryPlatformOrder->short_order_id,
                'Platform' => $orderItem->deliveryPlatformOrder->deliveryPlatform->name . ' ('. $orderItem->deliveryPlatformOrder->deliveryPlatformOperator->type .')',
                'Order Time' => $orderItem->deliveryPlatformOrder->order_created_at->toDateTimeString(),
                'Status' => DeliveryPlatformOrder::STATUS_MAPPING[$orderItem->deliveryPlatformOrder->status],
                'Vend ID' => $orderItem->deliveryPlatformOrder->vend_code,
                'Customer' => isset($orderItem->deliveryPlatformOrder->vend_json) ?
                                    $orderItem->deliveryPlatformOrder->vend_json['full_name'] :
                                    ($orderItem->deliveryPlatformOrder && $orderItem->deliveryPlatformOrder->deliveryProductMappingVend && $orderItem->deliveryPlatformOrder->deliveryProductMappingVend->vend && $orderItem->deliveryPlatformOrder->deliveryProductMappingVend->vend->latestVendBinding && $orderItem->deliveryPlatformOrder->deliveryProductMappingVend->vend->latestVendBinding->customer ? $orderItem->deliveryPlatformOrder->deliveryProductMappingVend->vend->latestVendBinding->customer->code. ' ' . $orderItem->deliveryPlatformOrder->deliveryProductMappingVend->vend->latestVendBinding->customer->name : ''),
                'Sys Order ID' => $orderItem->deliveryPlatformOrder->vend_transaction_order_id,
                'Channel' => $orderItem->orderItemVendChannels[0]->vend_channel_code,
                'Product Code' => isset($orderItem->product_json) ?
                                $orderItem->product_json['code'] :
                                ($orderItem->product ? $orderItem->product->code : '' ),
                'Product Name' => isset($orderItem->product_json) ?
                                $orderItem->product_json['name'] :
                                ($orderItem->product ? $orderItem->product->name : '' ),
                'Qty' => $orderItem->qty,
                'Subtotal' => $orderItem->amount / 100,
                'Order Grand Total' => $orderItem->deliveryPlatformOrder->subtotal_amount,
            ];
        });
    }

    public function requestCancelOrder($id)
    {
        $deliveryPlatformOrder = DeliveryPlatformOrder::findOrFail($id);

        $response = $this->deliveryPlatformService->cancelOrder($deliveryPlatformOrder);

        if($response) {
            return redirect()->back()->with('success', 'Order cancelled');
        }else {
            return redirect()->back()->with('error', 'Order cancel not successful');
        }
    }

    private function getDeliveryPlatformOrderQuery($request)
    {
        return DeliveryPlatformOrder::query()
        ->with([
            'deliveryPlatform:id,name,country_id,slug',
            'deliveryPlatformOrderItems',
            'deliveryProductMappingVend.deliveryProductMapping:id,name',
            'deliveryProductMappingVend.vend:id,code,name',
            'deliveryProductMappingVend.vend.latestVendBinding.customer:id,code,name',
            'deliveryPlatformOperator',
            'deliveryPlatformOrderComplaint',
            'deliveryPlatformOrderItems.deliveryProductMappingItem.product:id,code,name,is_active',
            'deliveryPlatformOrderItems.deliveryProductMappingItem.product.thumbnail',
            'deliveryPlatformOrderItems.orderItemVendChannels',

        ])
        ->when($request->delivery_platform_operator_id, function($query, $search) {
            if($search != 'all') {
                $query->where('delivery_platform_operator_id', $search);
            }
        })
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
        ->when($request->status, function ($query, $search) {
            if($search != 'all') {
                $query->where('status', $search);
            }
        })
        ->when($request->has_complaint, function ($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->has('deliveryPlatformOrderComplaint');
                }else {
                    $query->doesntHave('deliveryPlatformOrderComplaint');
                }
            }
        })
        ->when($request->sortKey, function($query, $search) use ($request) {
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        });
    }

    private function yieldOneByOne($items) {
        foreach($items as $item) {
            yield $item;
        }
    }
}
