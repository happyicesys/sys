<?php

namespace App\Http\Controllers;

use App\Http\Resources\CountryResource;
use App\Http\Resources\DeliveryPlatformResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PaymentGatewayResource;
use App\Http\Resources\VendResource;
use App\Models\Country;
use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Models\Operator;
use App\Models\OperatorPaymentGateway;
use App\Models\OperatorVend;
use App\Models\PaymentGateway;
use App\Models\Vend;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OperatorController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read operators']);
    }

    public function index(Request $request)
    {
        $timezones = DateTimeZone::listIdentifiers();
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Operator/Index', [
            // 'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            // 'deliveryPlatformOperatorTypes' => [
            //     DeliveryPlatformOperator::TYPE_SANDBOX,
            //     DeliveryPlatformOperator::TYPE_PRODUCTION
            // ],
            'operators' => OperatorResource::collection(
                Operator::with([
                    'address:id,postcode',
                    // 'deliveryPlatformOperators.deliveryPlatform',
                    'country:id,name,code,currency_name,currency_symbol',
                    // 'operatorPaymentGateways.paymentGateway',
                    'vends:id,code,name',
                    'vends.latestVendBinding.customer:id,code,name,person_id,virtual_customer_code,virtual_customer_prefix',
                    ])
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            // 'operatorPaymentGatewayTypes' => [
            //     OperatorPaymentGateway::TYPE_SANDBOX,
            //     OperatorPaymentGateway::TYPE_PRODUCTION
            // ],
            // 'timezones' => $timezones,
            // 'countryDeliveryPlatforms' =>
            //     DeliveryPlatformResource::collection(
            //         DeliveryPlatform::with(['country'])
            //         ->when($request->country_id, function($query, $search) {
            //             $query->where('country_id', $search);
            //         })
            //         ->orderBy('name')
            //         ->get()
            //     )
            // ,
            // 'countryPaymentGateways' =>
            //     PaymentGatewayResource::collection(
            //         PaymentGateway::with(['country'])
            //         ->when($request->country_id, function($query, $search) {
            //             $query->where('country_id', $search);
            //         })
            //         ->orderBy('name')
            //         ->get()
            //     )
            // ,
            // 'unbindedVends' => fn () =>
            //     VendResource::collection(
            //         Vend::with([
            //             'latestVendBinding.customer:id,code,name'
            //         ])->whereNotIn('id', function($query) use ($request) {
            //             $query->select('vend_id')
            //                 ->from('operator_vend')
            //                 ->where('operator_id', $request->operator_id);
            //         })
            //         ->orderBy('code')
            //         ->get()
            //     )
            // ,
            // 'operator' => fn() => OperatorResource::make(
            //     Operator::with([
            //         'address',
            //         'address.country',
            //         'country',
            //         'deliveryPlatformOperators.deliveryPlatform',
            //         'operatorPaymentGateways.paymentGateway',
            //         'vends',
            //         'vends.latestVendBinding.customer',
            //     ])
            //     ->when($request->name, function($query, $search) {
            //         $query->where('name', 'LIKE', "%{$search}%");
            //     })
            //     ->when($request->id, function($query, $search) {
            //         $query->where('id', $search);
            //     })
            //     ->first()
            // )
        ]);
    }

    public function create(Request $request)
    {
        $timezones = DateTimeZone::listIdentifiers();

        return Inertia::render('Operator/Create', [
            'countries' => CountryResource::collection(
                Country::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            ),
            'timezones' => $timezones,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $operator = Operator::query()
            ->with([
                'address',
                'address.country',
                'country',
                'deliveryPlatformOperators.deliveryPlatform',
                'operatorPaymentGateways.paymentGateway',
                'vends:id,code,name',
                'vends.latestVendBinding.customer:id,code,name,virtual_customer_code,virtual_customer_prefix',
            ])
            ->find($id);
        $timezones = DateTimeZone::listIdentifiers();

        return Inertia::render('Operator/Edit', [
            'countries' => CountryResource::collection(
                Country::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            ),
            'countryDeliveryPlatforms' =>
                DeliveryPlatformResource::collection(
                    DeliveryPlatform::with(['country'])
                    ->when($request->country_id, function($query, $search) {
                        $query->where('country_id', $search);
                    })
                    ->orderBy('name')
                    ->get()
                )
            ,
            'countryPaymentGateways' =>
                PaymentGatewayResource::collection(
                    PaymentGateway::with(['country'])
                    ->orderBy('name')
                    ->get()
                )
            ,
            'deliveryPlatformOperatorTypes' => [
                DeliveryPlatformOperator::TYPE_SANDBOX,
                DeliveryPlatformOperator::TYPE_PRODUCTION
            ],
            'operatorPaymentGatewayTypes' => [
                OperatorPaymentGateway::TYPE_SANDBOX,
                OperatorPaymentGateway::TYPE_PRODUCTION
            ],
            'operator' => OperatorResource::make(
                $operator
            ),
            'timezones' => $timezones,
            'type' => 'update',
        ]);
    }

    public function deleteDeliveryPlatformOperator($id)
    {
        $deliveryPlatformOperator = DeliveryPlatformOperator::findOrFail($id);
        $operatorId = $deliveryPlatformOperator->operator_id;

        $deliveryPlatformOperator->delete();

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function deleteOperatorPaymentGateway($id)
    {
        $operatorPaymentGateway = OperatorPaymentGateway::findOrFail($id);
        $operatorId = $operatorPaymentGateway->operator_id;

        $operatorPaymentGateway->delete();

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function deleteOperatorVend($id)
    {
        $operatorVend = OperatorVend::findOrFail($id);
        $operatorId = $operatorVend->operator_id;

        $operatorVend->delete();

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'country_id' => 'required',
            'name' => 'required',
            'timezone' => 'required',
        ]);

        if(!$request->has('gst_vat_rate') or $request->gst_vat_rate == null) {
            $request->merge(['gst_vat_rate' => 0]);
        }
        $operator = Operator::create($request->all());

        // return redirect()->route('operators');
        return redirect()->route('operators.edit', [$operator->id]);
    }

    public function storeDeliveryPlatformOperator(Request $request, $operatorId)
    {
        $operator = Operator::findOrFail($operatorId);
        $operator->deliveryPlatformOperators()->create($request->all());

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function storeOperatorPaymentGateway(Request $request, $operatorId)
    {
        // dd($request->all());
        $operator = Operator::findOrFail($operatorId);
        $operator->operatorPaymentGateways()->create($request->all());

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function update(Request $request, $operatorId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $operator = Operator::findOrFail($operatorId);
        $operator->update($request->all());

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function delete($operatorId)
    {
        $operator = Operator::findOrFail($operatorId);
        $operator->delete();

        return redirect()->route('operators');
    }

    public function bindVend(Request $request)
    {
        $operator = Operator::findOrFail($request->operator_id);
        $vend = Vend::where('code', $request->code)->firstOrFail();
        $operator->vends()->attach($vend->id);

        return redirect()->route('operators.edit', [$operator->id]);
    }

    public function unbindVend(Request $request)
    {
        $operator = Operator::findOrFail($request->operator_id);
        $operator->vends()->detach($request->vend_id);

        return redirect()->route('operators.edit', [$operator->id]);
    }

    public function bindDeliveryPlatform(Request $request, $id)
    {
        $operator = Operator::findOrFail($id);
        // dd($request->all());
        $createdDeliveryPlatformOperator = $operator->deliveryPlatformOperators()->create($request->all());
        if($request->has('oauth_client_id') and $request->has('oauth_client_secret')) {
            $createdDeliveryPlatformOperator->externalOauthToken()->updateOrCreate([
                'client_id' => $request->oauth_client_id,
                'client_secret' => $request->oauth_client_secret,
            ], [
                'granted_type' => $createdDeliveryPlatformOperator->deliveryPlatform->default_granted_type,
                'scopes' => $createdDeliveryPlatformOperator->deliveryPlatform->default_scopes,
            ]);
        }
    }

    public function unbindDeliveryPlatform($paymentGatewayOperatorId)
    {
        $paymentGatewayOperator = DeliveryPlatformOperator::findOrFail($paymentGatewayOperatorId);
        if($paymentGatewayOperator->externalOauthToken()->exists()) {
            $paymentGatewayOperator->externalOauthToken()->delete();
        }
        $paymentGatewayOperator->delete();
    }
}
