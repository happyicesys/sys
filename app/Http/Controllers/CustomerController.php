<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCustomerData;
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
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;
        $className = get_class(new Customer());
        // dd($request->statuses);

        return Inertia::render('Customer/Index', [
            'customers' => CustomerResource::collection(
                Customer::with([
                    'attachments',
                    'billingAddress',
                    'category',
                    'category.categoryGroup',
                    'deliveryAddress',
                    'firstTransaction',
                    'priceTemplate',
                    'profile',
                    'status',
                    'tagBindings',
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
                    ->when($request->handled_by, fn($query, $input) => $query->where('handled_by', $input))
                    ->when($request->name, fn($query, $input) => $query->where('name', 'LIKE', '%'.$input.'%'))
                    ->when($request->price_template_id, fn($query, $input) => $query->where('price_template_id', $input))
                    ->when($request->profile_id, fn($query, $input) => $query->where('profile_id', $input))
                    ->when($request->statuses, fn($query, $input) => $query->whereIn('status_id', $input))
                    ->when($request->zone_id, fn($query, $input) => $query->where('zone_id', $input))
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
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
            'statuses' => StatusResource::collection(
                Status::query()
                    ->where('classname', $className)
                    ->orderBy('sequence')
                    ->get()
            ),
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

    public function migrate(Request $request)
    {
        VendData::create([
            'ip_address' => $request->ip(),
            'value' => $request->all(),
        ]);
        ProcessCustomerData::dispatch($request->all(), null);
    }

    public function syncNextDeliveryDate()
    {
        $response = Http::get(env('CMS_URL') . '/api/people/last-invoice-date');
        $people = $response->collect();

        if($people) {
            foreach($people as $person) {
                $customer = Customer::whereHas('vendBinding', function($query) use ($person) {
                    $query->where('is_active', true)
                        ->whereHas('vend', function($query) use ($person) {
                            $query->where('code', $person['vend_code']);
                        });
                })->first();

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
