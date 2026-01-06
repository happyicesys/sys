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
use App\Http\Resources\VendPrefixResource;
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
use App\Models\VendPrefix;
use App\Models\Zone;
use App\Services\HistoryService;
use App\Services\MapService;
use App\Traits\HasFilter;
use App\Traits\SearchAddress;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CustomerController extends Controller
{
    use HasFilter, SearchAddress;

    protected $historyService;
    protected $mapService;

    public function __construct(HistoryService $historyService)
    {
        $this->historyService = $historyService;
        $this->mapService = new MapService();
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
            'category',
            'category.categoryGroup',
            'contact',
            'deliveryAddress',
            'firstTransaction',
            'operator',
            'profile',
            'status',
            'tagBindings',
            'vend.vendPrefix',
            'zone'
        ])
            ->leftJoin('addresses', function ($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\\Models\\Customer')
                    ->where('addresses.type', '=', 2)
                    ->limit(1);
            })
            ->leftJoin('operators', 'customers.operator_id', '=', 'operators.id')
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
            ->leftJoin(DB::raw('
                (
                    SELECT vend_id, SUM(amount * qty) AS total_stock_amount, SUM(amount * capacity) AS total_full_load_amount
                    FROM vend_channels
                    WHERE is_active = true
                    AND capacity > 0
                    GROUP BY vend_id
                ) AS vc
            '), 'vc.vend_id', '=', 'vends.id')
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
                'vc.total_full_load_amount',
                'vends.code as vend_code',
                'zones.name as zone_name',
                DB::raw('
                    (JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.vend_records_thirty_days_amount_average")) *30 /100)/
                    (vc.total_full_load_amount / 100) AS thirty_days_over_full_load_ratio
                ')
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

        // Use OptionsService to load all dropdown options
        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Customer/Index', [
            'customers' => CustomerResource::collection(
                $customers
            ),
            'categories' => $optionsService->categories($className),
            'categoryGroups' => $optionsService->categoryGroups($className),
            'cmsEndpoint' => env('CMS_URL'),
            'days' => Customer::DAYS_MAPPING,
            'frequencyPerWeekOptions' => Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING,
            'locationTypeOptions' => $optionsService->locationTypes(),
            'mapApiKey' => $this->mapService->getMapApiKeyByUser(auth()->user()),
            'operatorOptions' => $optionsService->operators(),

            'profiles' => $optionsService->profiles(),
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
            'tags' => $optionsService->tags($className),
            'users' => $optionsService->users(),
            'vendModelOptions' => $optionsService->vendModels(),
            'vendPrefixOptions' => $optionsService->vendPrefixes(),
            'zoneOptions' => $optionsService->zones(),
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
        // Use OptionsService for dropdown options
        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Customer/Create', [
            'cmsCustomerOptions' => env('CMS_URL') ? (Http::get(env('CMS_URL') . '/api/vends/unbind')->collect() ?
                Http::get(env('CMS_URL') . '/api/vends/unbind')->collect()->whereNotIn('id', Customer::select('person_id')->pluck('person_id'))->all() :
                []) : [],
            'countries' => $optionsService->countries(),
            'customer' => new Customer(),
            'operatorOptions' => $optionsService->operators(),
            'vendOptions' => Vend::query()
                ->select('id', 'code', 'customer_id')
                ->where('customer_id', null)
                ->orderBy('code')
                ->get(),
            'cmsEndpoint' => env('CMS_URL'),
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

        if ($request->selling_price_type) {
            $type = $request->selling_price_type;
        } else {
            $type = $customerInit->selling_price_type;
        }

        $customer = Customer::query()
            ->with([
                'attachments',
                'billingAddress',
                'category',
                'category.categoryGroup',
                'contact',
                'customerVendBindings.vend:id,code,customer_id',
                'customerVendBindings.vendPrefix',
                'deliveryAddress',
                'firstTransaction',
                'photos',
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

        // Use OptionsService for dropdown options
        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Customer/Edit', [
            'cmsEndpoint' => env('CMS_URL'),
            'countries' => $optionsService->countries(),
            'days' => Customer::DAYS_MAPPING,
            'frequencyPerWeekOptions' => Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING,
            'locationTypeOptions' => $optionsService->locationTypes(),
            'operatorOptions' => $optionsService->operators(),
            'sellingPriceTypeOptions' => collect(SellingPrice::TYPE_MAPPINGS),
            'vendOptions' => Vend::query()
                ->select('id', 'code', 'customer_id')
                ->where('customer_id', null)
                ->orderBy('code')
                ->get(),
            'customer' => $customer,
            'type' => 'update',
            'zoneOptions' => $optionsService->zones(),
        ]);
    }

    public function getMap(Request $request)
    {
        $input = collect($request->all());

        $customers = Customer::query()
            ->with([
                'contact',
                'vend:id,code,customer_id',
                'deliveryAddress'
            ])
            ->whereIn('id', $input->pluck('customer_id'))
            ->get()
            ->sortBy(function ($customer) use ($input) {
                return $input->firstWhere('customer_id', $customer->id)['sequence'];
            })->values(); // Resetting the keys of the collection


        return CustomerResource::collection($customers);
    }

    // retrieve all or single vendcodes from sys.happyice
    public function getCustomersByPersonID($personID = null)
    {
        $customers = Customer::query()
            ->with(['vends'])
            ->when($personID, fn($query, $input) => $query->where('person_id', $input))
            ->get();

        SyncVendCustomerCms::dispatch($personID, null);
        return $customers;
    }

    // public function migrate(Request $request)
    // {
    //     $value = $request->all();
    //     SyncVendCustomerCms::dispatch(null, $value['id']);
    // }

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

            if ($request->operator_id) {
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

    public function syncFromCms(Request $request)
    {
        $response = Http::get(env('CMS_URL') . '/api/people');
        $people = $response->collect();

        if ($people) {
            foreach ($people as $person) {
                $customer = Customer::where('person_id', $person['id'])->first();

                if ($customer) {
                    $customer->update([
                        'cms_customer' => $person,
                    ]);
                }
            }
        }

        return true;
    }

    public function syncCmsInvoiceItems(Request $request)
    {
        $customers = Customer::query()
            ->whereIn('id', $request->customerIDs)
            ->get();

        if ($customers) {
            foreach ($customers as $customer) {
                SyncTransactionItemCMS::dispatch($customer->id)->onQueue('default');
                // SyncTransactionItemCMS::dispatchSync($customer->id);
            }
        }
    }

    public function syncNextDeliveryDate($people = [])
    {
        if (!$people) {
            // get all people from cms
            $response = Http::get(env('CMS_URL') . '/api/people/last-invoice-date');
            $people = $response->collect();
        }

        if (empty($people)) {
            return true;
        }

        // Batch load all customers by person_id
        $personIds = collect($people)->pluck('id')->toArray();
        $customers = Customer::whereIn('person_id', $personIds)
            ->get()
            ->keyBy('person_id');

        // Batch load all ops job items by cms_transaction_id
        $transactionIds = collect($people)
            ->pluck('next_transaction_id')
            ->filter()
            ->toArray();
        $opsJobItems = OpsJobItem::whereIn('cms_transaction_id', $transactionIds)
            ->get()
            ->keyBy('cms_transaction_id');

        // Prepare bulk updates
        $now = now();

        foreach ($people as $person) {
            $customer = $customers->get($person['id']);

            if ($customer) {
                $customer->update([
                    'cms_invoice_history' => $person,
                    'last_invoice_date' => $person['last_delivery_date'],
                    'next_invoice_date' => $person['next_delivery_date'],
                    'updated_at' => $now,
                ]);
            }

            if ($person['next_transaction_id'] && $person['next_transaction_sequence']) {
                $opsJobItem = $opsJobItems->get($person['next_transaction_id']);

                if ($opsJobItem) {
                    $opsJobItem->update([
                        'sequence' => $person['next_transaction_sequence'],
                        'updated_at' => $now,
                    ]);
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

        if (isset($requestCustomerArr['is_restricted_access']) and $requestCustomerArr['is_restricted_access'] === 'true') {
            $requestCustomerArr['is_restricted_access'] = true;
        } else {
            $requestCustomerArr['is_restricted_access'] = false;
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
            if (!$vend->customer_id && $customer->id) {
                $isMovement = true;
            }
            $vend->customer_id = $customer->id;
            $vend->save();

            if ($isMovement) {
                $this->historyService->syncVendCustomerMovement($vend, $customer, true);
            }
        } else {
            // dd('here1111', $request->all());
            $customer->update($request->customer);

            if ($request->customer['contact'] && isset($request->customer['contact']['name'])) {
                if ($customer->contact) {
                    $customer->contact->update($request->customer['contact']);
                } else {
                    $customer->contact()->create($request->customer['contact']);
                }
            }

            if ($request->customer['address'] && isset($request->customer['address']['country_id'])) {
                $customer->deliveryAddress()->updateOrCreate([
                    'type' => Customer::ADDRESS_TYPE_DELIVERY,
                ], $request->customer['address']);
            }

            if ($request->customer and isset($request->customer['vend_id']) and $request->customer['vend_id']) {
                $vend = Vend::find($request->customer['vend_id']);

                $isMovement = false;
                if (!$vend->customer_id && $customer->id) {
                    $isMovement = true;
                }
                $vend->customer_id = $customer->id;
                $vend->save();

                if ($isMovement) {
                    $this->historyService->syncVendCustomerMovement($vend, $customer, true);
                }
            }
        }

        if ($customer->deliveryAddress) {
            if ((!$customer->deliveryAddress->latitude or !$customer->deliveryAddress->longitude) and $customer->deliveryAddress->country->code == 'SG') {
                $location = $this->getAddressResult($customer->deliveryAddress->postcode);

                if ($location) {
                    $customer->deliveryAddress->update([
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                    ]);
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

    public function uploadPhoto(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/customers';
            $storedPath = $files->storePublicly($dir);
            $fileName = basename($storedPath);
            $url = Storage::url($storedPath);
            $customer->photos()->create([
                'type' => 2,
                'full_url' => $url,
                'local_url' => $dir . '/' . $fileName,
            ]);
        }
        return true;
    }
}
