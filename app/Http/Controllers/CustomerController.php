<?php

namespace App\Http\Controllers;
use App\Jobs\SyncVendCustomerCms;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PriceTemplateResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\ZoneResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\PriceTemplate;
use App\Models\Profile;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendData;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'is_cms' => $request->is_cms ? $request->is_cms : 'all',
            'is_active' => $request->is_active ? $request->is_active : 'all',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
        ]);
        $className = get_class(new Customer());

        // dd($request->all());
        return Inertia::render('Customer/Index', [
            'customers' => CustomerResource::collection(
                Customer::with([
                        'attachments',
                        'billingAddress',
                        'category',
                        'category.categoryGroup',
                        'contact',
                        'deliveryAddress',
                        'firstTransaction',
                        'profile',
                        'status',
                        'tagBindings',
                        'vend',
                        'zone'
                    ])
                    ->when($request->categories, function($query, $search) {
                        $query->whereHas('categories', function($query) use ($search) {
                            $query->whereIn('id', $search);
                        });
                    })
                    ->when($request->categoryGroups, fn($query, $input) => $query->whereHas('category.categoryGroup', function($query) use ($input) {
                        $query->whereIn('id', $input);
                    }))
                    ->when($request->code, fn($query, $input) => $query->where('code', 'LIKE', '%'.$input.'%'))
                    ->when($request->created_in, fn($query, $input) => $query->whereDate('created_at', '>=', Carbon::createFromFormat('m-Y', $input)->startOfMonth())->whereDate('created_at', '<=', Carbon::createFromFormat('m-Y', $input)->endOfMonth()))
                    ->when($request->customer, function($query, $search) {
                        $query->where(function($query) use ($search) {
                            $query->where('customers.virtual_customer_prefix', 'LIKE', "{$search}%")
                                    ->orWhere('customers.virtual_customer_code', 'LIKE', "{$search}%")
                                    ->orWhere('customers.name', 'LIKE', "%{$search}%");
                            });
                    })
                    ->when($request->is_active, function($query, $search) use ($request) {
                        if($search != 'all') {
                            $query->where('customers.is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                        }
                    })
                    ->when($request->is_cms, function($query, $search) {
                        if($search != 'all') {
                            $searchBoolean = filter_var($search, FILTER_VALIDATE_BOOLEAN);
                            if($searchBoolean)
                                $query->whereNotNull('person_id');
                            else {
                                $query->whereNull('person_id');
                            }
                        }
                    })
                    ->when($request->handled_by, fn($query, $input) => $query->where('handled_by', $input))
                    // ->when($request->name, fn($query, $input) => $query->where('name', 'LIKE', '%'.$input.'%'))
                    ->when($request->price_template_id, fn($query, $input) => $query->where('price_template_id', $input))
                    ->when($request->profile_id, fn($query, $input) => $query->where('profile_id', $input))
                    ->when($request->ref_id, function($query, $search) {
                        $query->where('id', 'LIKE', $search - 10000);
                    })
                    ->when($request->status, fn($query, $input) => $query->where('status_id', $input))
                    ->when($request->vend_code, function($query, $search) {
                        $query->whereIn('id',
                            Vend::where('code', 'LIKE', '%'.$search.'%')
                            ->pluck('customer_id')
                        );
                    })
                    ->when($request->zone_id, fn($query, $input) => $query->where('zone_id', $input))
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
            'categories' => CategoryResource::collection(
                Category::query()
                    ->where('classname', $className)
                    ->orderBy('sequence')
                    ->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::query()
                    ->whereHas('categories', function($query) use ($className){
                        $query->where('classname', $className);
                    })->orderBy('name')->get()
            ),
            'priceTemplates' => PriceTemplateResource::collection(
                PriceTemplate::query()
                    ->orderBy('name')
                    ->get()
            ),
            'profiles' => ProfileResource::collection(
                Profile::query()
                    ->orderBy('name')
                    ->get()
            ),
            'statuses' => [
                [
                    'id' => 'all',
                    'name' => 'All',
                ],
                ...collect(Customer::STATUSES_MAPPING)->map(function($status, $index) {
                    return [
                        'id' => $index,
                        'name' => $status,
                    ];
                })
            ],
            'tags' => TagResource::collection(
                Tag::query()
                    ->orderBy('name')
                    ->get()
            ),
            'users' => UserResource::collection(
                User::query()
                    ->orderBy('name')
                    ->get()
            ),
            'zones' => ZoneResource::collection(
                Zone::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function create()
    {

        return Inertia::render('Customer/Create', [
            'cmsCustomerOptions' => Http::get(env('CMS_URL') . '/api/vends/unbind')->collect() ? Http::get(env('CMS_URL') . '/api/vends/unbind')->collect()->whereNotIn('id', Customer::select('person_id')->pluck('person_id'))->all() : [],
            'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            'customer' => new Customer(),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendOptions' => Vend::query()
                ->select('id', 'code', 'customer_id')
                ->where('customer_id', null)
                ->orderBy('code')
                ->get(),
            'type' => 'create',
        ]);
    }

    public function delete($id)
    {
        $customer = Customer::find($id);
        $customer->delete();

        return redirect()->route('customers');
    }

    public function edit(Request $request, $id)
    {
        $customer = Customer::query()
        ->with([
            'attachments',
            'billingAddress',
            'category',
            'category.categoryGroup',
            'contact',
            'deliveryAddress',
            'firstTransaction',
            'profile',
            'status',
            'tagBindings',
            'vend',
            'zone'
        ])
        ->find($id);

        return Inertia::render('Customer/Edit', [
            'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendOptions' => Vend::query()
                ->select('id', 'code', 'customer_id')
                ->where('customer_id', null)
                ->orderBy('code')
                ->get(),
            'customer' => $customer,
            'type' => 'update',
        ]);
    }

    // retrieve all or single vendcodes from sys.happyice
    public function getCustomersByPersonID($personID = null)
    {
        $customers = Customer::query()
            ->with(['vends'])
            ->when($personID, fn($query, $input) => $query->where('person_id', $input))
            ->get();

        return $customers;
    }

    public function migrate(Request $request)
    {
        $value = $request->all();
        SyncVendCustomerCms::dispatch(null, $value['id']);
    }

    public function search(Request $request)
    {
        $search = $request->search;
        $customers = Customer::query()
            ->with([
                'operator:id,name',
                'vend:id,code,customer_id'
            ])
            ->has('vend')
            ->where(function($query) use ($search) {
                $query->where('name', 'LIKE', '%'.$search.'%')
                    ->orWhere('virtual_customer_code', 'LIKE', '%'.$search.'%')
                    ->orWhere('virtual_customer_prefix', 'LIKE', '%'.$search.'%')
                    ->orWhereHas('vend', function($query) use ($search){
                        $query->where('code', 'LIKE', '%'.$search.'%');
                    });
            })
            ->whereNull('operator_id')
            ->get();

        return $customers;
    }

    public function store(Request $request)
    {
        $request->validate([
            'operator_id' => 'required',
        ]);

        // dd($request->all(), $request->customer, $request->contact, $request->address);
        if($request->is_existing) {
            $request->validate([
                'cms_customer_id' => 'required',
            ]);
            SyncVendCustomerCms::dispatchSync($request->cms_customer_id, null);

            $customer = Customer::where('person_id', $request->cms_customer_id)->first();
        } else {
            $request->validate([
                'name' => 'required',
            ]);
            $customer = Customer::create($request->all());

            if($request->contact and isset($request->contact['name']) and $request->contact['name']) {
                $customer->contact()->updateOrCreate($request->contact);
            }

            if($request->address and isset($request->address['postcode']) and $request->address['postcode']){
                $customer->deliveryAddress()->updateOrCreate([
                    'type' => Customer::ADDRESS_TYPE_DELIVERY,
                ], $request->address);
            }
        }

        return redirect()->route('customers.edit', [$customer->id]);
    }

    public function syncNextDeliveryDate()
    {
        $response = Http::get(env('CMS_URL') . '/api/people/last-invoice-date');
        $people = $response->collect();

        if($people) {
            foreach($people as $person) {
                $customer = Customer::where('person_id', $person['id'])->first();

                if($customer) {
                    $customer->update([
                        'cms_invoice_history' => $person,
                        'last_invoice_date' => $person['last_delivery_date'],
                        'next_invoice_date' => $person['next_delivery_date']
                    ]);
                }
            }
        }

        return true;
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);
        $vend = Vend::find($request->id);

        $requestCustomerArr = $request->customer;
        if(isset($requestCustomerArr['is_active']) and $requestCustomerArr['is_active'] === 'true') {
            $requestCustomerArr['is_active'] = true;
        } else {
            $requestCustomerArr['is_active'] = false;
        }
        $request->merge([
            'customer' => $requestCustomerArr,
        ]);
        // dd($request->all());

        if(!$customer) {
            if($request->is_existing) {
                $request->validate([
                    'customer_id' => 'required',
                ]);
                $customer = Customer::where('id', $request->customer_id)->first();
            } else {
                $request->validate([
                    'customer.operator_id' => 'required',
                    'customer.name' => 'required',
                ]);

                $customer = Customer::create($request->customer);
                // dd($request->customer['contact'], $request->customer['address']);
                if($request->customer['contact'] && isset($request->customer['contact']['name']) && $request->customer['contact']['name']) {
                    $customer->contact()->updateOrCreate($request->customer['contact']);
                }

                if($request->customer['address'] && isset($request->customer['address']['postcode']) && $request->customer['address']['postcode']) {
                    $customer->deliveryAddress()->updateOrCreate([
                        'type' => Customer::ADDRESS_TYPE_DELIVERY,
                    ], $request->customer['address']);
                }
            }
            $vend->customer_id = $customer->id;
            $vend->save();
        }else {
            // dd('here1111', $request->all());
            $customer->update($request->customer);

            if($request->customer['contact'] && isset($request->customer['contact']['name']) && $request->customer['contact']['name']) {
                $customer->contact()->updateOrCreate($request->customer['contact']);
            }

            if($request->customer['address'] && isset($request->customer['address']['postcode']) && $request->customer['address']['postcode']) {
                $customer->deliveryAddress()->updateOrCreate([
                    'type' => Customer::ADDRESS_TYPE_DELIVERY,
                ], $request->customer['address']);
            }

            if($request->customer and isset($request->customer['vend_id']) and $request->customer['vend_id']){
                $vend = Vend::find($request->customer['vend_id']);
                $vend->customer_id = $customer->id;
                $vend->save();
            }
        }

        if($customer and $customer->person_id and $vend) {
            SyncVendCustomerCms::dispatchSync($customer->person_id, $vend->id);
        }

        return redirect()->back();
    }
}
