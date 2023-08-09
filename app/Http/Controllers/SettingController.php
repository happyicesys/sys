<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendDBResource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CategoryGroup;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\Vend;
use App\Traits\HasFilter;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class SettingController extends Controller
{
    use HasFilter;

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'code']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : true]);
        $className = get_class(new Customer());
        if(!isset($request->is_binded_customer)) {
            if(
                env('VEND_INIT_BINDED') and
                (auth()->user()->hasRole('superadmin') or
                auth()->user()->hasRole('admin') or
                auth()->user()->hasRole('supervisor') or
                auth()->user()->hasRole('driver'))
            ) {
                $request->merge(['is_binded_customer' => 'true']);
            }else {
                $request->merge(['is_binded_customer' => 'all']);
            }
        }

        $vends = Vend::query()
            ->with([
                'latestOperator:id,code,name',
                'latestVendBinding.customer:id,code,name',
            ])
            ->filterIndex($request)
            ->select(
                'id',
                'vends.id',
                'vends.begin_date',
                'vends.code',
                'vends.apk_ver_json',
                'vends.serial_num',
                'vends.last_updated_at',
                'vends.name',
                'vends.termination_date',
                'vends.firmware_ver',
                'vends.last_updated_at',
                'vends.private_key'
            );

        $vends = $vends->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Setting/Index', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vends' => VendResource::collection(
                $vends
            ),
        ]);
    }

    public function editOrCreate($id = null, $type)
    {
        if($id) {
            $vend = Vend::findOrFail($id);
        }else {
            $vend = new Vend();
        }

        $response = Http::get(env('CMS_URL') . '/api/vends');


        return Inertia::render('Setting/Edit', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'adminCustomerOptions' => $response->collect(),
            'vend' => $vend,
            'type' => $type,
        ]);
    }
}
