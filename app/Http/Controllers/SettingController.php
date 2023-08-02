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

        $vends = DB::table('vends')
            ->leftJoin('vend_bindings', function($query) {
                $query->on('vend_bindings.vend_id', '=', 'vends.id')
                        ->where('is_active', true)
                        ->latest('begin_date')
                        ->limit(1);
            })
            ->leftJoin('customers', 'customers.id', '=', 'vend_bindings.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operator_vend', function($query) {
                $query->on('operator_vend.vend_id', '=', 'vends.id')
                        ->latest('operator_vend.begin_date')
                        ->limit(1);
            })
            ->leftJoin('operators', 'operators.id', '=', 'operator_vend.operator_id')
            ->select(
                'operator_vend.operator_id',
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
                'vends.private_key',
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'customers.location_type_id',
                'location_types.name AS location_type_name',
                'operators.name AS operator_name',
            );
        $vends = $this->filterVendsDB($vends, $request);
        // $vends = $this->filterOperatorDB($vends);

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
            'vends' => VendDBResource::collection(
                $vends
            ),
        ]);
    }

    public function edit($id)
    {
        $vend = Vend::findOrFail($id);

        return Inertia::render('Setting/Edit', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vend' => VendResource::make($vend),
        ]);
    }
}
