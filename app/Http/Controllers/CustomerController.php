<?php

namespace App\Http\Controllers;
use App\Jobs\SyncSingleCustomer;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\PriceTemplateResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\ZoneResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Customer;
use App\Models\PriceTemplate;
use App\Models\Profile;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
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
            'status' => $request->status ? $request->status : Customer::STATUS_ACTIVE,
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
        ]);
        $className = get_class(new Customer());

        return Inertia::render('Customer/Index', [
            'customers' => CustomerResource::collection(
                Customer::with([
                    'attachments',
                    'billingAddress',
                    'category',
                    'category.categoryGroup',
                    'deliveryAddress',
                    'firstTransaction',
                    'profile',
                    'status',
                    'tagBindings',
                    'vendBindings.vend',
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
                    ->when($request->customer, fn($query, $input) => $query->where('customer_json->prefix', 'LIKE', "{$input}%")->orWhere('customer_json->code', 'LIKE', "{$input}%")->orWhere('name', 'LIKE', "%{$input}%"))
                    ->when($request->handled_by, fn($query, $input) => $query->where('handled_by', $input))
                    ->when($request->name, fn($query, $input) => $query->where('name', 'LIKE', '%'.$input.'%'))
                    ->when($request->price_template_id, fn($query, $input) => $query->where('price_template_id', $input))
                    ->when($request->profile_id, fn($query, $input) => $query->where('profile_id', $input))
                    ->when($request->status, fn($query, $input) => $query->where('status_id', $input))
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

    // retrieve all or single vendcodes from sys.happyice
    public function getCustomersByPersonID($personID = null)
    {
        $customers = Customer::query()
            ->with(['latestVendBinding.vend'])
            ->when($personID, fn($query, $input) => $query->where('person_id', $input))
            ->get();

        return $customers;
    }

    public function migrate(Request $request)
    {
        $value = $request->all();
        SyncSingleCustomer::dispatch($value['id']);
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
}
