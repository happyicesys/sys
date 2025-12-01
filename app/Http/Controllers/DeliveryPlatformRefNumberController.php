<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryPlatformRefNumberResource;
use App\Http\Resources\OperatorResource;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\Operator;
use App\Models\DeliveryProductMappingVend;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryPlatformRefNumberController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        // Default status filter to 'true' (Yes = Active)
        if (!$request->has('is_active')) {
            $request->merge(['is_active' => 'true']);
        }

        return Inertia::render('DeliveryPlatformRefNumber/Index', [
            'deliveryPlatformRefNumbers' => DeliveryPlatformRefNumberResource::collection(
                DeliveryPlatformRefNumber::with(['operator', 'currentDeliveryProductMappingVend.vend.vendPrefix'])
                    ->withCount('deliveryProductMappingVends')
                    ->withCount([
                        'deliveryProductMappingVends as active_delivery_product_mapping_vends_count' => function ($query) {
                            $query->whereNull('end_date');
                        },
                    ])
                    ->addSelect([
                        'current_vend_code_sort' => DeliveryProductMappingVend::select('vend_code')
                            ->whereColumn('delivery_product_mapping_vend.delivery_platform_ref_number_id', 'delivery_platform_ref_numbers.id')
                            ->whereNull('end_date')
                            ->latest('id')
                            ->limit(1)
                    ])
                    ->when($request->ref_number, function ($query, $search) {
                        $query->where('ref_number', 'LIKE', "%{$search}%");
                    })
                    ->when($request->current_vend_code, function ($query, $search) {
                        $query->whereHas('currentDeliveryProductMappingVend', function ($q) use ($search) {
                            $q->where('vend_code', 'LIKE', "{$search}%");
                        });
                    })
                    ->when($request->has('is_active') && $request->is_active !== 'all', function ($query) use ($request) {
                        if ($request->is_active === 'true') {
                            $query->whereHas('deliveryProductMappingVends', function ($q) {
                                $q->whereNull('end_date');
                            });
                        } elseif ($request->is_active === 'false') {
                            $query->whereDoesntHave('deliveryProductMappingVends', function ($q) {
                                $q->whereNull('end_date');
                            });
                        }
                    })
                    ->when($request->operators, function ($query, $search) {
                        if (!in_array('all', $search)) {
                            $query->whereIn('operator_id', $search);
                        }
                    })
                    ->when($request->vend_prefixes, function ($query, $search) {
                        if (!in_array('all', $search)) {
                            $query->whereHas('currentDeliveryProductMappingVend.vend', function ($q) use ($search) {
                                $q->whereIn('vend_prefix_id', $search);
                            });
                        }
                    })
                    ->when($sortKey, function ($query) use ($sortKey, $sortBy) {
                        $direction = filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';
                        if ($sortKey === 'current_vend_code') {
                            $query->orderBy('current_vend_code_sort', $direction);
                        } elseif ($sortKey === 'status') {
                            $query->orderBy('active_delivery_product_mapping_vends_count', $direction);
                        } else {
                            $query->orderBy($sortKey, $direction);
                        }
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => app(\App\Services\OptionsService::class)->vendPrefixes(),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('DeliveryPlatformRefNumber/Create', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'operator_id' => ['required', 'exists:operators,id'],
            'ref_number' => ['required', 'string', 'max:255', 'unique:delivery_platform_ref_numbers,ref_number'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'in:true,false'],
        ]);

        $status = ($request->is_active ?? 'true') === 'true'
            ? DeliveryPlatformRefNumber::STATUS_ACTIVE
            : DeliveryPlatformRefNumber::STATUS_INACTIVE;

        DeliveryPlatformRefNumber::create([
            'delivery_platform_id' => 1,
            'operator_id' => $validated['operator_id'],
            'ref_number' => $validated['ref_number'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => $status,
        ]);

        return redirect()->route('delivery-platform-ref-numbers');
    }

    public function edit(Request $request, $id)
    {
        $refNumber = DeliveryPlatformRefNumber::with([
            'operator',
            'deliveryProductMappingVends' => function ($query) {
                $query->with([
                    'vend:id,code,name,customer_id',
                    'vend.customer:id,code,name,virtual_customer_prefix,virtual_customer_code',
                    'deliveryProductMapping:id,name',
                ])->orderByDesc('created_at');
            },
        ])->findOrFail($id);

        return Inertia::render('DeliveryPlatformRefNumber/Edit', [
            'deliveryPlatformRefNumber' => new DeliveryPlatformRefNumberResource($refNumber),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
        ]);
    }

    public function update(Request $request, $id)
    {
        $refNumber = DeliveryPlatformRefNumber::findOrFail($id);

        $validated = $request->validate([
            'operator_id' => ['required', 'exists:operators,id'],
            'ref_number' => ['required', 'string', 'max:255', 'unique:delivery_platform_ref_numbers,ref_number,' . $refNumber->id],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'in:true,false'],
        ]);

        $status = $request->is_active === 'true'
            ? DeliveryPlatformRefNumber::STATUS_ACTIVE
            : DeliveryPlatformRefNumber::STATUS_INACTIVE;

        $refNumber->update([
            'operator_id' => $validated['operator_id'],
            'ref_number' => $validated['ref_number'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => $status,
        ]);

        return redirect()->route('delivery-platform-ref-numbers');
    }
}
