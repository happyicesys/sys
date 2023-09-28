<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryProductMappingResource;
use App\Http\Resources\DeliveryProductMappingItemResource;
use App\Http\Resources\DeliveryPlatformOperatorResource;
use App\Http\Resources\OperatorResource;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingItem;
use App\Models\Operator;
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
        // dd($request->all());
        return Inertia::render('DeliveryPlatform/Form', [
            'categoryApiOptions' => Inertia::lazy(fn() => [
                // 'grab' => route('delivery-platform.get-categories', ['operatorId' => 1, 'type' => 'grab']),
                $this->deliveryPlatformService->getCategories($request->operator_id, $request->type),
            ]),
            'deliveryPlatformOperators' => Inertia::lazy(fn() =>
                DeliveryPlatformOperatorResource::collection(
                    DeliveryPlatformOperator::query()
                    ->where('operator_id', $request->operator_id)
                    ->get(),
                )
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'type' => $request->id ? 'edit' : 'create',
        ]);
    }
}
