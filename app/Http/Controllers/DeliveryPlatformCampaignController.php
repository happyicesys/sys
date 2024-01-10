<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPlatformCampaign;
use App\Models\DeliveryPlatformCampaignItem;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
use App\Http\Resources\DeliveryPlatformCampaignResource;
use App\Http\Resources\DeliveryPlatformOperatorResource;
use App\Http\Resources\DeliveryProductMappingResource;
use App\Services\DeliveryPlatformCampaignService;
use App\Services\DeliveryPlatformService;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryPlatformCampaignController extends Controller
{
    use GetUserTimezone;

    protected $deliveryPlatformCampaignService;
    protected $deliveryPlatformService;

    public function __construct(DeliveryPlatformCampaignService $deliveryPlatformCampaignService, DeliveryPlatformService $deliveryPlatformService)
    {
        $this->deliveryPlatformService = new DeliveryPlatformService();
        $this->deliveryPlatformCampaignService = $deliveryPlatformCampaignService;
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

        return Inertia::render('DeliveryPlatformCampaign/Index', [
            'deliveryPlatformCampaigns' => DeliveryPlatformCampaignResource::collection(
                DeliveryPlatformCampaign::query()
                    ->with([
                        'deliveryPlatformCampaignItems',
                        'deliveryPlatformOperator.deliveryPlatform',
                        'deliveryProductMapping:id,name',
                    ])
                    ->filterIndex($request)
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
            'deliveryPlatformOperatorOptions' => DeliveryPlatformOperatorResource::collection(
                DeliveryPlatformOperator::with('deliveryPlatform')->get()
            ),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('DeliveryPlatformCampaign/Create', [
            'deliveryPlatformOperatorOptions' => DeliveryPlatformOperatorResource::collection(
                DeliveryPlatformOperator::query()
                    ->with('deliveryPlatform')
                    ->when($request->delivery_product_mapping_id, function($query, $search){
                        $query->whereHas('deliveryProductMappings', function($query) use ($search) {
                            $query->where('id', $search);
                        });
                    })
                    ->get()
            ),
            'deliveryProductMappingOptions' =>
                DeliveryProductMappingResource::collection(
                    DeliveryProductMapping::all()
            ),
        ]);
    }

    public function store(Request $request)
    {
        // handle creation form validation, array
        $request->validate([
            'name' => 'required',
            'delivery_product_mapping_id' => 'required|unique:delivery_platform_campaigns,delivery_product_mapping_id',
            'datetime_from' => 'required',
            'datetime_to' => 'required',
        ], [
            'name.required' => 'Name is required',
            'delivery_product_mapping_id.required' => 'Delivery Product Mapping is required',
            'delivery_platform_operator_id.required' => 'Delivery Platform is required',
            'datetime_from.required' => 'Begin Date is required',
            'datetime_to.required' => 'End Date is required',
        ]);

        $deliveryPlatformCampaign = DeliveryPlatformCampaign::create($request->all());

        return redirect()->route('delivery-platform-campaigns.edit', [$deliveryPlatformCampaign->id]);
    }

    public function edit(Request $request, $id)
    {
        $deliveryPlatformCampaign = DeliveryPlatformCampaign::query()
            ->with([
                'deliveryPlatformCampaignItems.deliveryPlatformCampaignItemVends',
                'deliveryPlatformOperator.deliveryPlatform',
                'deliveryProductMapping:id,category_json,name',
                'deliveryProductMapping.deliveryProductMappingItems.product.thumbnail',
                'deliveryProductMapping.deliveryProductMappingBulks.deliveryProductMappingBulkItems.deliveryProductMappingItem.product.thumbnail',
            ])
            ->findOrFail($id);

        return Inertia::render('DeliveryPlatformCampaign/Edit', [
            'deliveryPlatformCampaignItemOptions' => $this->deliveryPlatformCampaignService->getItemOptions($deliveryPlatformCampaign),
            'deliveryPlatformCampaign' => DeliveryPlatformCampaignResource::make(
                $deliveryPlatformCampaign
            ),
            'type' => 'edit',
        ]);
    }

    public function createItem(Request $request, $id)
    {
        $deliveryPlatformCampaign = DeliveryPlatformCampaign::findOrFail($id);
        $deliveryPlatformCampaign->deliveryPlatformCampaignItems()->create([
            'datetime_from' => $deliveryPlatformCampaign->datetime_from,
            'datetime_to' => $deliveryPlatformCampaign->datetime_to,
            'is_active' => true,
            'items_json' => $request->delivery_product_mapping_items ? $request->delivery_product_mapping_items : [$request->category],
            'settings_json' => [
                'totalCount' => $request->total_count ? $request->total_count : null,
                'totalCountPerUser' => $request->total_count_per_user ? $request->total_count_per_user : null,
                'eaterType' => $request->delivery_platform_campaign_item_scope_eater_type,
                'minBasketAmount' => $request->min_basket_amount ? $request->min_basket_amount : null,
                'qty' => $request->qty ? $request->qty : null,
                'type' => $request->delivery_platform_campaign_item,
                'cap' => $request->cap ? $request->cap : null,
                'value' => $request->promo_value,
                'scope' => $request->delivery_platform_campaign_item_scope,
                'objectIDs' => $request->delivery_product_mapping_items ? collect($request->delivery_product_mapping_items)->pluck('id')->map(function($id) {
                    return (string)$id;
                })->toArray() : collect([$request->category])->pluck('id')->toArray(),
            ],
            'settings_label' => $request->settings_label,
            'settings_name' => $request->settings_name,
        ]);

        return redirect()->route('delivery-platform-campaigns.edit', [$deliveryPlatformCampaign->id]);
    }

    public function deleteItem($deliveryPlatformCampaignItemID)
    {
        $deliveryPlatformCampaignItem = DeliveryPlatformCampaignItem::findOrFail($deliveryPlatformCampaignItemID);
        $deliveryPlatformCampaignItem->delete();

        return redirect()->route('delivery-platform-campaigns.edit', [$deliveryPlatformCampaignItem->deliveryPlatformCampaign->id]);
    }

    public function submitPlatform($id)
    {
        $deliveryPlatformCampaign = DeliveryPlatformCampaign::findOrFail($id);
        $this->deliveryPlatformCampaignService->syncItemVends($deliveryPlatformCampaign);

        $this->deliveryPlatformCampaignService->syncCampaigns($deliveryPlatformCampaign);

        return redirect()->route('delivery-platform-campaigns.edit', [$deliveryPlatformCampaign->id]);
    }
}
