<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PriceTemplateResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\ZoneResource;
use App\Jobs\SyncVendCustomerCms;
use App\Jobs\SyncTransactionItemCMS;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\OpsJobItem;
use App\Models\PriceTemplate;
use App\Models\Profile;
use App\Models\SellingPrice;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendModel;
use App\Models\Zone;
use App\Services\HistoryService;
use App\Traits\HasFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CustomerController extends Controller
{
    use HasFilter;

    protected $historyService;

    public function __construct(
        HistoryService $historyService,
    )
    {
        $this->historyService = $historyService;
    }

    public function index(Request $request)
    {
        $request->merge([
            'is_binded_vend' => $request->is_binded_vend ? $request->is_binded_vend : 'all',
            'is_cms' => $request->is_cms ? $request->is_cms : 'all',
            'is_active' => $request->is_active ? $request->is_active : 'true',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'customers.id',
            'sortBy' => $request->sortBy ? $request->sortBy : 'false',
        ]);
        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [auth()->user()->operator_id, Operator::where('code', 'HIMD')->first() ? Operator::where('code', 'HIMD')->first()->id : null]]);
        //     }else {
        //         $request->merge(['operators' => ['all']]);
        //     }
        // }
        $className = get_class(new Customer());

        $customers = Customer::with([
                'attachments',
                'billingAddress',
                'category',
                'category.categoryGroup',
                'contact',
                'deliveryAddress',
                'firstTransaction',
                'operator',
                'profile',
                'status',
                'tagBindings',
                'vend',
                'zone'
            ])
            ->leftJoin('addresses', function($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                        ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                        ->where('addresses.type', '=', 2)
                        ->limit(1);
            })
            ->leftJoin('operators', 'customers.operator_id', '=', 'operators.id')
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
            ->select(
                'addresses.postcode as postcode',
                'customers.*',
                'customers.id',
                'customers.begin_date as begin_date',
                'customers.frequency_per_week_status',
                'customers.operator_id',
                'customers.preferred_visit_days_json',
                'customers.zone_id',
                'operators.code as operator_code',
                'operators.name as operator_name',
                'vends.code as vend_code',
                'zones.name as zone_name'
            )
            ->filterIndex($request);

        $customers = $this->filterOperator($customers);

        $customers = $customers
            ->paginate(
                $request->numberPerPage === 'All' ?
                    10000 :
                    $request->numberPerPage
            )
            ->withQueryString();

        return Inertia::render('Customer/Index', [
            'customers' => CustomerResource::collection(
                $customers
            ),
            'categories' => CategoryResource::collection(
                Category::query()
                    ->where('classname', $className)
                    ->orderBy('sequence')
                    ->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::query()
                    ->whereHas('categories', function ($query) use ($className) {
                        $query->where('classname', $className);
                    })->orderBy('name')->get()
            ),
            'cmsEndpoint' => env('CMS_URL'),
            'days' => Customer::DAYS_MAPPING,
            'frequencyPerWeekOptions' => Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING,
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('name')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
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
            'sellingPriceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'statuses' => [
                [
                    'id' => 'all',
                    'name' => 'All',
                ],
                ...collect(Customer::STATUSES_MAPPING)->map(function ($status, $index) {
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
            'vendModelOptions' => VendModelResource::collection(
                VendModel::orderBy('name')->get()
            ),
            'zoneOptions' => ZoneResource::collection(
                Zone::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function bindVend(Request $request, $id)
    {
        $customer = Customer::find($id);
        $vend = Vend::find($request->vendID);

        if ($customer and $vend) {
            $vend->customer_id = $customer->id;
            $vend->save();
            SyncVendCustomerCms::dispatchSync($customer->person_id, $vend->id);
        }

        return redirect()->back();
    }

    public function create()
    {

        return Inertia::render('Customer/Create', [
            'cmsCustomerOptions' => Http::get(env('CMS_URL') . '/api/vends/unbind')->collect() ?
                Http::get(env('CMS_URL') . '/api/vends/unbind')->collect()->whereNotIn('id', Customer::select('person_id')->pluck('person_id'))->all() :
                [],
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

    public function disconnectCms($id)
    {
        $customer = Customer::find($id);
        $customer->person_id = null;
        $customer->save();

        return redirect()->route('customers.edit', [$id]);
    }

    public function edit(Request $request, $id)
    {
        $customerInit = Customer::findOrFail($id);

        if($request->selling_price_type) {
            $type = $request->selling_price_type;
        }else {
            $type = $customerInit->selling_price_type;
        }

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
            'vend:id,code,customer_id,product_mapping_id',
            'vend.productMapping.attachments' => function ($query) use ($type) {
                // $query->when($type, function ($query, $type) {
                //     $query->where('type', $type);
                // });
                $query->where('type', $type);
            },
            'vend.vendChannels:id,amount,amount2,code,vend_id,product_id',
            'vend.vendChannels.product:id,name,code,desc',
            'vend.vendChannels.product.sellingPrices' => function ($query) use ($type) {
                // $query->when($type, function ($query, $type) {
                //     $query->where('type', $type);
                // });
                $query->where('type', $type);
            },
            'vend.vendChannels.product.thumbnail',
            'zone',
        ])
        ->find($id);

        return Inertia::render('Customer/Edit', [
            'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            'days' => Customer::DAYS_MAPPING,
            'frequencyPerWeekOptions' => Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING,
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('name')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'sellingPriceTypeOptions' => collect(SellingPrice::TYPE_MAPPINGS),
            'vendOptions' => Vend::query()
                ->select('id', 'code', 'customer_id')
                ->where('customer_id', null)
                ->orderBy('code')
                ->get(),
            'customer' => $customer,
            'type' => 'update',
            'zoneOptions' => ZoneResource::collection(
                Zone::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function getAddress($customerID)
    {
        $customer = Customer::query()
            ->with([
                'contact',
                'vend:id,code,customer_id',
                'deliveryAddress'
                ])
            ->find($customerID);

        return CustomerResource::make($customer);
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
            ->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('virtual_customer_code', 'LIKE', '%' . $search . '%')
                    ->orWhere('virtual_customer_prefix', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('vend', function ($query) use ($search) {
                        $query->where('code', 'LIKE', '%' . $search . '%');
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

        if ($request->is_existing) {
            $request->validate([
                'cms_customer_id' => 'required',
            ]);
            SyncVendCustomerCms::dispatchSync($request->cms_customer_id, null);

            $customer = Customer::where('person_id', $request->cms_customer_id)->first();

            if($request->operator_id) {
                $customer->update([
                    'operator_id' => $request->operator_id,
                ]);
            }
        } else {
            $request->validate([
                'name' => 'required',
            ]);
            $customer = Customer::create($request->all());

            if ($request->contact and isset($request->contact['name']) and $request->contact['name']) {
                $customer->contact()->updateOrCreate($request->contact);
            }

            if ($request->address and isset($request->address['postcode']) and $request->address['postcode']) {
                $customer->deliveryAddress()->updateOrCreate([
                    'type' => Customer::ADDRESS_TYPE_DELIVERY,
                ], $request->address);
            }
        }

        return redirect()->route('customers.edit', [$customer->id]);
    }

    public function syncCmsInvoiceItems(Request $request)
    {
        $customers = Customer::query()
            ->whereIn('id', $request->customerIDs)
            ->get();

        if($customers) {
            foreach($customers as $customer) {
                SyncTransactionItemCMS::dispatch($customer->id)->onQueue('default');
                // SyncTransactionItemCMS::dispatchSync($customer->id);
            }
        }
    }

    public function syncNextDeliveryDate($people = [])
    {
        if(!$people) {
            // get all people from cms
            $response = Http::get(env('CMS_URL') . '/api/people/last-invoice-date');
            $people = $response->collect();
        }

        if ($people) {
            foreach ($people as $person) {
                $customer = Customer::where('person_id', $person['id'])->first();

                if ($customer) {
                    $customer->update([
                        'cms_invoice_history' => $person,
                        'last_invoice_date' => $person['last_delivery_date'],
                        'next_invoice_date' => $person['next_delivery_date'],
                    ]);
                }
                if($person['next_transaction_id'] && $person['next_transaction_sequence']) {
                    $opsJobItem = OpsJobItem::where('cms_transaction_id', $person['next_transaction_id'])->first();

                    if($opsJobItem) {
                        $opsJobItem->update([
                            'sequence' => $person['next_transaction_sequence'],
                        ]);
                    }
                }
            }
        }

        return true;
    }

    public function update(Request $request, $id)
    {
        // dd($request->all());
        $customer = Customer::find($id);
        $vend = Vend::find($request->id);

        $requestCustomerArr = $request->customer;
        if (isset($requestCustomerArr['is_active']) and $requestCustomerArr['is_active'] === 'true') {
            $requestCustomerArr['is_active'] = true;
        } else {
            $requestCustomerArr['is_active'] = false;
        }
        $request->merge([
            'customer' => $requestCustomerArr,
        ]);

        if (!$customer) {
            if ($request->is_existing) {
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

                if ($request->customer['contact'] && isset($request->customer['contact']['name']) && $request->customer['contact']['name']) {
                    $customer->contact()->updateOrCreate($request->customer['contact']);
                }

                if ($request->customer['address'] && isset($request->customer['address']['postcode']) && $request->customer['address']['postcode']) {
                    $customer->deliveryAddress()->updateOrCreate([
                        'type' => Customer::ADDRESS_TYPE_DELIVERY,
                    ], $request->customer['address']);
                }
            }
            $isMovement = false;
            if(!$vend->customer_id && $customer->id) {
                $isMovement = true;
            }
            $vend->customer_id = $customer->id;
            $vend->save();

            if($isMovement) {
                $this->historyService->syncVendCustomerMovement($vend, $customer, true);
            }
        } else {
            // dd('here1111', $request->all());
            $customer->update($request->customer);

            if ($request->customer['contact'] && isset($request->customer['contact']['name'])) {
                $customer->contact()->updateOrCreate([
                    'id' => $customer->contact->id,
                ], $request->customer['contact']);
            }

            if ($request->customer['address'] && isset($request->customer['address']['country_id'])) {
                $customer->deliveryAddress()->updateOrCreate([
                    'type' => Customer::ADDRESS_TYPE_DELIVERY,
                ], $request->customer['address']);
            }

            if ($request->customer and isset($request->customer['vend_id']) and $request->customer['vend_id']) {
                $vend = Vend::find($request->customer['vend_id']);

                $isMovement = false;
                if(!$vend->customer_id && $customer->id) {
                    $isMovement = true;
                }
                $vend->customer_id = $customer->id;
                $vend->save();

                if($isMovement) {
                    $this->historyService->syncVendCustomerMovement($vend, $customer, true);
                }
            }
        }

        if ($customer->vend) {
            $customer->vend->update([
                'operator_id' => $customer->operator_id,
            ]);
        }

        if ($customer and $customer->person_id and $vend) {
            SyncVendCustomerCms::dispatchSync($customer->person_id, $vend->id);
        }

        return redirect()->back();
    }

    public function uploadAttachment(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/customers';
            $storedPath = $files->storePublicly($dir);
            $fileName = basename($storedPath);
            $url = Storage::url($storedPath);
            $customer->attachments()->create([
                'type' => 1,
                'full_url' => $url,
                'local_url' => $dir . '/' . $fileName,
            ]);
        }
        return true;
    }
}
