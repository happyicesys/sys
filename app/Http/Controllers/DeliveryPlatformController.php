<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryProductMappingResource;
use App\Http\Resources\DeliveryProductMappingItemResource;
use App\Http\Resources\OperatorResource;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingItem;
use App\Models\Operator;
use App\Models\Vend;
use App\Jobs\SyncDeliveryPlatformOauthByOperator;
use App\Services\DeliveryPlatformService;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Laravel\Passport\Token;
use Inertia\Inertia;

class DeliveryPlatformController extends Controller
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
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
        ]);
    }

    public function getCategories($operatorId, $type)
    {
        try {
            $operator = Operator::findOrFail($operatorId);
            $response = $this->deliveryPlatformService->getCategories($operator, $type);
            return $response;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    public function getMenu(Request $request)
    {
        $merchantId = $request->merchantID;
        $partnerMerchantID = $request->partnerMerchantID;


    }

    public function getOauth($operatorId, $type)
    {
        try {
            SyncDeliveryPlatformOauthByOperator::dispatch($operatorId, $type);
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    public function sendOauth(Request $request)
    {
        try {
            $clients = Client::with('tokens')
                    ->where('id', $request->client_id)
                    ->where('secret', $request->client_secret)
                    ->get();

            // dd($clients->toArray());

            $operator = Operator::findOrFail($operatorId);
            $response = $this->deliveryPlatformService->getOauth($operator, $type);
            if(!$this->deliveryPlatformService->getDeliveryPlatformOperator()->externalOauthToken()->exists()) {
                throw new \Exception('Please set init Oauth Client ID and Client Secret');
            }
            $this->deliveryPlatformService->getDeliveryPlatformOperator()->externalOauthToken()->update($response);
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }
}
