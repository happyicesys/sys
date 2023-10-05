<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryProductMappingResource;
use App\Http\Resources\DeliveryProductMappingItemResource;
use App\Http\Resources\DeliveryPlatformOperatorResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\ProductMappingItemResource;
use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingItem;
use App\Models\Operator;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Services\DeliveryPlatformService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryProductMappingController extends Controller
{
    protected $deliveryPlatformService;

    public function __construct(DeliveryPlatformService $deliveryPlatformService)
    {
        $this->deliveryPlatformService = $deliveryPlatformService;
    }

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('DeliveryPlatform/Index', [
            'deliveryProductMappings' => DeliveryProductMappingResource::collection(
                DeliveryProductMapping::query()
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function createOrUpdate(Request $request)
    {
        return Inertia::render('DeliveryPlatform/Form', [
            'categoryApiOptions' => Inertia::lazy(fn() =>[
                $this->deliveryPlatformService->getCategories(Operator::find($request->operator_id), DeliveryPlatformOperator::find($request->delivery_platform_operator_id)->deliveryPlatform->slug),
            ]),
            'operatorOptions' => OperatorResource::collection(
                Operator::query()
                    ->with('deliveryPlatformOperators.deliveryPlatform')
                    ->orderBy('name')
                    ->get()
            ),
            'productMappingItems' => Inertia::lazy(fn() => [
                ProductMappingItemResource::collection(
                    ProductMappingItem::query()
                        ->with('product.thumbnail')
                        ->where('product_mapping_id', $request->product_mapping_id)
                        ->get()
                )
            ]),
            'productMappingOptions' => Inertia::lazy(fn() =>[
                ProductMappingResource::collection(
                    ProductMapping::query()
                        ->where('operator_id', $request->operator_id)
                        ->get()
                ),
            ]),
            'productOptions' => ProductResource::collection(
                Product::with([
                    'thumbnail'
                ])
                ->where('is_inventory', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
            ),
            'type' => $request->id ? 'edit' : 'create',
        ]);
    }
}
