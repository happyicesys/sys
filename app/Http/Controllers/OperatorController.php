<?php

namespace App\Http\Controllers;

use App\Http\Resources\CountryResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PaymentGatewayResource;
use App\Http\Resources\VendResource;
use App\Models\Country;
use App\Models\Operator;
use App\Models\OperatorPaymentGateway;
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
        // $this->middleware(['permission: read operators']);
    }

    public function index(Request $request)
    {
        $timezones = DateTimeZone::listIdentifiers();
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Operator/Index', [
            'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            'operators' => OperatorResource::collection(
                Operator::with([
                    'address:id,postcode',
                    // 'address.country',
                    'country:id,name,code,currency_name,currency_symbol',
                    'operatorPaymentGateways.paymentGateway',
                    'vends:id,code,name',
                    'vends.latestVendBinding.customer:id,code,name',
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
            'operatorPaymentGatewayTypes' => [
                OperatorPaymentGateway::TYPE_SANDBOX,
                OperatorPaymentGateway::TYPE_PRODUCTION
            ],
            'timezones' => $timezones,
            'countryPaymentGateways' =>
                PaymentGatewayResource::collection(
                    PaymentGateway::with(['country'])
                    ->orderBy('name')
                    ->get()
                )
            ,
            'unbindedVends' => fn () =>
                VendResource::collection(
                    Vend::with([
                        'latestVendBinding.customer:id,code,name'
                    ])->whereNotIn('id', function($query) use ($request) {
                        $query->select('vend_id')
                            ->from('operator_vend')
                            ->where('operator_id', $request->operator_id);
                    })
                    ->orderBy('code')
                    ->get()

                    // ->whereDoesntHave('operators', function($query) use ($request) {
                    //     $query->where('operators.id', '!=', $request->operator_id);
                    // })

                )
            ,
            'operator' => fn() => OperatorResource::make(
                Operator::with([
                    'address',
                    'address.country',
                    'country',
                    'operatorPaymentGateways.paymentGateway',
                    'vends',
                    'vends.latestVendBinding.customer',
                ])
                ->when($request->name, function($query, $search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                })
                ->when($request->id, function($query, $search) {
                    $query->where('id', $search);
                })
                ->first()
            )
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        if(!$request->has('gst_vat_rate') or $request->gst_vat_rate == null) {
            $request->merge(['gst_vat_rate' => 0]);
        }
        Operator::create($request->all());

        return redirect()->route('operators');
    }

    public function update(Request $request, $operatorId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $operator = Operator::findOrFail($operatorId);
        $operator->update($request->all());

        $originalVends = collect($request->vends)->transform(function($vend) {
            return $vend['id'];
        });
        $editedVends = collect($request->operator['vends'])->transform(function($vend) {
            return $vend['id'];
        });

        $removeVends = $originalVends->diff($editedVends);
        $addVends = $editedVends->diff($originalVends);

        if($removeVends) {
            foreach($removeVends as $removeVend) {
                $operator->vends()->detach($removeVend);
            }
        }
        if($addVends) {
            foreach($addVends as $addVend) {
                $operator->vends()->attach($addVend);
            }
        }

        if($request->has('operator')) {
            if($request->has('paymentGateways')) {
                $operator->operatorPaymentGateways()->delete();
                if($request->operator['operatorPaymentGateways']) {
                    foreach($request->operator['operatorPaymentGateways'] as $operatorPaymentGateway) {
                        $operatorPaymentGateway['payment_gateway_id'] = $operatorPaymentGateway['paymentGateway']['id'];
                        $operator->operatorPaymentGateways()->create($operatorPaymentGateway);
                    }
                }
            }
        }

        return redirect()->route('operators');
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
        $operator->vends()->attach($request->vend_id);

        return redirect()->route('operators');
    }

    public function unbindVend(Request $request)
    {
        $operator = Operator::findOrFail($request->operator_id);
        $operator->vends()->detach($request->vend_id);

        return redirect()->route('operators');
    }
}
