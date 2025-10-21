<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryDBResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\LocationTypeDBResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductDBResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductStockCountResource;
use App\Http\Resources\SalesReportResource;
use App\Http\Resources\StockCountResource;
use App\Http\Resources\StockCountItemResource;
use App\Http\Resources\StockCountDayGraphResource;
use App\Http\Resources\VendContractResource;
use App\Http\Resources\VendDBResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendSnapshotDBResource;
use App\Http\Resources\VendTransactionGraphResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\UnitCost;
use App\Models\Vend;
use App\Models\VendContract;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Models\VendTransaction;
use App\Traits\GetUserTimezone;
use App\Traits\HasFilter;
use App\Traits\HasMonthOption;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;

class ReportController extends Controller
{
    use HasFilter, HasMonthOption, GetUserTimezone;

    public function __construct()
    {
        $this->middleware(['permission:read reports']);
    }

    public function indexSales(Request $request, $type)
    {

        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [
        //             auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
        //             auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
        //         ]]);
        //     }else {
        //         $request->merge(['operators' => [auth()->user()->operator_id]]);
        //     }
        // }

        $request->merge([
            // 'operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id,
            'visited' => isset($request->visited) ? $request->visited : true,
            'is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : 'true'),
        ]);

        if($request->currentFilterDate) {
            if($request->currentFilterDate != '-1') {
                $request->merge(['date_from' => explode(',',$request->currentFilterDate)[0]]);
                $request->merge(['date_to' => explode(',',$request->currentFilterDate)[1]]);
            }
        }
        // if(!$request->date_from) {
        //     $request->merge(['date_from' => Carbon::today()->setTimezone($this->getUserTimezone())->toDateString()]);
        // }
        // if(!$request->date_to) {
        //     $request->merge(['date_to' => Carbon::today()->setTimezone($this->getUserTimezone())->toDateString()]);
        // }

        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 30;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'amount';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $categoryClassName = get_class(new Customer());
        $modelName = 'vends';

        switch($type) {
            case 'category':
                $modelName = 'categories';
                break;
            case 'location-type':
                $modelName = 'location_types';
                break;
            case 'product':
                $modelName = 'products';
                break;
            case 'operator':
                $modelName = 'operators';
                break;
            case 'vend':
                $modelName = 'vends';
                break;
            case 'customer':
                $modelName = 'customers';
                break;
        }

        $items = $this->getSalesQuery($request, $modelName);
        $totals = $this->getSalesReportTotals($items);
        $items = $items->when($request->sortKey, function($query, $search) use ($request) {
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        });

        $items = $items->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/Sales/Index', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $categoryClassName)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $categoryClassName)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'reportDateOptions' => $this->getReportDateOptions(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendContractOptions' => VendContractResource::collection(
                VendContract::orderBy('name')->get()
            ),
            'vendModelOptions' => VendModelResource::collection(
                VendModel::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
            'items' => SalesReportResource::collection($items),
            'totals' => $totals,
        ]);
    }


    public function indexGpVm(Request $request)
    {
        // $request->merge(['operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id]);
        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [
        //             auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
        //             auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
        //         ]]);
        //     }else {
        //         $request->merge(['operators' => [auth()->user()->operator_id]]);
        //     }
        // }
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : 'true')]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 30;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;

        $className = get_class(new Customer());

        $vends = $this->getUnitCostByVendQuery($request);
        $totals = $this->getSalesSubTotal($vends);
        $vends = $vends->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
        ->withQueryString();

        return Inertia::render('Report/Gp/IndexVm', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
            'totals' => $totals,
            'vends' => VendDBResource::collection($vends),
        ]);
    }

    public function indexGpProduct(Request $request)
    {
        // $request->merge(['operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id]);
        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [
        //             auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
        //             auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
        //         ]]);
        //     }else {
        //         $request->merge(['operators' => [auth()->user()->operator_id]]);
        //     }
        // }
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : 'true')]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 30;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $className = get_class(new Customer());

        $products = $this->getUnitCostByProductQuery($request);
        $totals = $this->getSalesSubTotal($products);
        $products = $products->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/Gp/IndexProduct', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
            'totals' => $totals,
            'products' => ProductDBResource::collection($products),
        ]);
    }

    public function indexGpCategory(Request $request)
    {
        // $request->merge(['operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id]);
        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [
        //             auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
        //             auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
        //         ]]);
        //     }else {
        //         $request->merge(['operators' => [auth()->user()->operator_id]]);
        //     }
        // }
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $className = get_class(new Customer());

        $categories = $this->getUnitCostByCategoryQuery($request);
        $totals = $this->getSalesSubTotal($categories);
        $categories = $categories->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/Gp/IndexCategory', [
            'categoryOptions' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
            'totals' => $totals,
            'categories' => CategoryDBResource::collection($categories),
        ]);
    }

    public function indexGpLocationType(Request $request)
    {
        // $request->merge(['operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id]);
        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [
        //             auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
        //             auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
        //         ]]);
        //     }else {
        //         $request->merge(['operators' => [auth()->user()->operator_id]]);
        //     }
        // }
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $request->is_binded_customer = auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : false);
        $className = get_class(new Customer());

        $locationTypes = $this->getUnitCostByLocationTypeQuery($request);
        $totals = $this->getSalesSubTotal($locationTypes);
        $locationTypes = $locationTypes->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/Gp/IndexLocationType', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
            'totals' => $totals,
            'locationTypes' => LocationTypeDBResource::collection($locationTypes),
        ]);
    }

    public function indexSnapshot(Request $request)
    {
        if(!$request->operators) {
            if(auth()->user()->operator->code == 'HIPL') {
                $request->merge(['operators' => array_filter([
                    auth()->user()->operator_id,
                    Operator::where('code', 'HIMD')->first()?->id,
                    Operator::where('code', 'LEA')->first()?->id,
                    Operator::where('code', 'DCVIC')->first()?->id,
                    Operator::where('code', 'HIESG')->first()?->id,
                    Operator::where('code', 'IP')->first()?->id,
                ])]);
            }else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
        $request->merge(['currentMonth' => isset($request->currentMonth) ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone())]);
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : false)]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'month_number';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $className = get_class(new Customer());

        $vendSnapshots = $this->getSnapshotQuery($request);
        // dd($vendSnapshots->get()->toArray());
        $vendSnapshots = $vendSnapshots->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/IndexSnapshot', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
            'vendSnapshots' => VendSnapshotDBResource::collection($vendSnapshots),
        ]);
    }

    public function indexStockCount(Request $request)
    {
        // ---- Operators default
        if(!$request->operators) {
            if(auth()->user()->operator->code == 'HIPL') {
                $request->merge(['operators' => [
                    auth()->user()->operator_id,
                    Operator::where('code', 'HIMD')->first()?->id,
                    Operator::where('code', 'LEA')->first()?->id,
                    Operator::where('code', 'DCVIC')->first()?->id,
                    Operator::where('code', 'HIESG')->first()?->id,
                    Operator::where('code', 'IP')->first()?->id,
                ]]);
            }else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }

        // ---- Date range from preset/custom
        $cfd = $request->input('currentFilterDate');

        // Frontend might send an object {id: "..."}; normalize to id string
        if (is_array($cfd) && isset($cfd['id'])) {
            $cfd = $cfd['id'];
            $request->merge(['currentFilterDate' => $cfd]);
        }

        if ($cfd) {
            if ($cfd !== '-1') {
                // Expect "YYYY-MM-DD,YYYY-MM-DD"
                [$df, $dt] = explode(',', (string)$cfd);
                $request->merge(['date_from' => $df, 'date_to' => $dt]);
            } else {
                $tz = $this->getUserTimezone();
                $df = Carbon::parse($request->date)->setTimezone($tz)->toDateString();
                $dt = Carbon::parse($request->date)->setTimezone($tz)->toDateString();
                $request->merge(['date_from' => $df, 'date_to' => $dt]);
            }
        } else {
            $today = Carbon::today($this->getUserTimezone())->toDateString();
            $request->merge(['date_from' => $today, 'date_to' => $today]);
        }

        // Swap if user sent from > to
        if (strtotime($request->date_from) > strtotime($request->date_to)) {
            $tmp = $request->date_from;
            $request->merge(['date_from' => $request->date_to, 'date_to' => $tmp]);
        }

        // ---- Misc defaults
        $request->merge(['visited' => $request->boolean('visited')]); // default false
        // keep sortKey/sortBy as they’ll be used by the pivot query for allowed keys
        $request->merge([
            'sortKey' => $request->input('sortKey', 'product_code'),
            'sortBy'  => $request->input('sortBy', false),
        ]);
        // numberPerPage is read inside the pivot query
        if (!$request->filled('numberPerPage')) {
            $request->merge(['numberPerPage' => 100]);
        }

        // ---- Use the server-side pivot (D0/D1/D2)
        [$paginator, $pivotDates, $totals] = $this->getStockCountPivot3dQuery($request);

        $stockCounts = [
            'data' => ProductStockCountResource::collection(
                collect($paginator->items())
            )->resolve(), // plain array of transformed rows

            // links array (works across Laravel versions)
            'links' => method_exists($paginator, 'linkCollection')
                ? $paginator->linkCollection()->toArray()
                : ($paginator->toArray()['links'] ?? []),

            // meta block your Paginator component expects
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from'         => $paginator->firstItem(),
                'last_page'    => $paginator->lastPage(),
                'path'         => $paginator->path(),
                'per_page'     => $paginator->perPage(),
                'to'           => $paginator->lastItem(),
                'total'        => $paginator->total(),
            ],
        ];

        // ---- Render
        return Inertia::render('Report/IndexStockCount', [
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productOptions' => ProductResource::collection(
                Product::where('is_inventory', true)->orderBy('name')->orderBy('code')->get()
            ),
            'reportDateOptions' => $this->getReportStockCountDateOptions(),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),

            // Pivot payload (note: this is a DB paginator, not Eloquent models)
            'stockCounts' => $stockCounts,   // rows have *_d0/_d1/_d2 fields
            'pivotDates'  => $pivotDates,    // { d0, d1, d2 } for table headers
            'totals'      => $totals,
        ]);
    }

    public function indexStockCountDashboard(Request $request)
    {
        // ---- Default operators
        if (!$request->operators) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge(['operators' => [
                    auth()->user()->operator_id,
                    Operator::where('code', 'HIMD')->first()?->id,
                    Operator::where('code', 'LEA')->first()?->id,
                    Operator::where('code', 'DCVIC')->first()?->id,
                    Operator::where('code', 'HIESG')->first()?->id,
                    Operator::where('code', 'IP')->first()?->id,
                ]]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }

        // ---- Build graphs (they already honor operators, vendPrefixes, codes, etc)
        $dayGraph = $this->getStockCountDayGraph($request);
        $qtyGraph = $this->getStockCountQtyDayGraph($request);

        return Inertia::render('Report/IndexStockCountDashboard', [
            'dayGraphData'        => StockCountDayGraphResource::collection($dayGraph),
            'qtyGraphData'        => StockCountDayGraphResource::collection($qtyGraph),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'operatorOptions'     => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productOptions'      => ProductResource::collection(
                Product::where('is_inventory', true)->orderBy('name')->orderBy('code')->get()
            ),
            'vendPrefixOptions'   => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
        ]);
    }



    public function exportUnitCostVendExcel(Request $request)
    {
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = isset($request->sortBy) ? $request->sortBy : false;

        $vends = $this->getUnitCostByVendQuery($request)->get();

        return (new FastExcel($this->yieldOneByOne($vends)))->download('UnitCostByVend_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vend) {
            return [
                'ID' => $vend->code,
                'Customer Name' => $vend->customer_code &&
                                    $vend->customer_name ?
                                    $vend->customer_code.''.$vend->customer_name :
                                    $vend->name,
                'Sales# (thisMth)' => $vend->this_month_count,
                'Sales$ (thisMth)' => $vend->this_month_revenue/ 100,
                'GP (thisMth)' => $vend->this_month_gross_profit/ 100,
                'GM (thisMth)' => $vend->this_month_gross_profit_margin,
                'Sales# (lastMth)' => $vend->last_month_count,
                'Sales$ (lastMth)' => $vend->last_month_revenue/ 100,
                'GP (lastMth)' => $vend->last_month_gross_profit/ 100,
                'GM (lastMth)' => $vend->last_month_gross_profit_margin,
                'Sales# (last2Mth)' => $vend->last_two_month_count,
                'Sales$ (last2Mth)' => $vend->last_two_month_revenue/ 100,
                'GP (last2Mth)' => $vend->last_two_month_gross_profit/ 100,
                'GM (last2Mth)' => $vend->last_two_month_gross_profit_margin,
            ];
        });
    }

    public function exportStockCountExcel(Request $request)
    {
        // ------- mirror the defaults/normalization from indexStockCount -------

        // Default operators
        if(!$request->operators) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge(['operators' => [
                    auth()->user()->operator_id,
                    Operator::where('code', 'HIMD')->first()?->id,
                    Operator::where('code', 'LEA')->first()?->id,
                    Operator::where('code', 'DCVIC')->first()?->id,
                    Operator::where('code', 'HIESG')->first()?->id,
                    Operator::where('code', 'IP')->first()?->id,
                ]]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }

        // Date range from preset/custom
        $cfd = $request->input('currentFilterDate');
        if (is_array($cfd) && isset($cfd['id'])) {
            $cfd = $cfd['id'];
            $request->merge(['currentFilterDate' => $cfd]);
        }

        if ($cfd) {
            if ($cfd !== '-1') {
                [$df, $dt] = explode(',', (string)$cfd);
                $request->merge(['date_from' => $df, 'date_to' => $dt]);
            } else {
                $tz = $this->getUserTimezone();
                $df = Carbon::parse($request->date)->setTimezone($tz)->toDateString();
                $dt = Carbon::parse($request->date)->setTimezone($tz)->toDateString();
                $request->merge(['date_from' => $df, 'date_to' => $dt]);
            }
        } else {
            $today = Carbon::today($this->getUserTimezone())->toDateString();
            $request->merge(['date_from' => $today, 'date_to' => $today]);
        }

        // Swap if needed
        if (strtotime($request->date_from) > strtotime($request->date_to)) {
            $tmp = $request->date_from;
            $request->merge(['date_from' => $request->date_to, 'date_to' => $tmp]);
        }

        // Sorting & page size (export = all)
        $request->merge([
            'visited'       => $request->boolean('visited'),
            'sortKey'       => $request->input('sortKey', 'product_code'),
            'sortBy'        => $request->input('sortBy', false),
            'numberPerPage' => 'All',
        ]);

        // ------- same pivot as page -------
        [$paginator, $pivotDates, $totals] = $this->getStockCountPivot3dQuery($request);
        $rows = collect($paginator->items());

        // d0 = yesterday of end date, d1 = -2 days, d2 = -3 days
        $d0 = Carbon::parse($pivotDates['d0'])->toDateString();
        $d1 = Carbon::parse($pivotDates['d1'])->toDateString();
        $d2 = Carbon::parse($pivotDates['d2'])->toDateString();

        // ----- Build a single canonical header list (order matters) -----
        $headers = [
            'Product ID',
            'Product Name',

            "{$d0} Unit Cost",
            "{$d0} Stock Value",
            "{$d0} Qty in Machine",
            "{$d0} Qty in Warehouse",
            "{$d0} Stock Cost",
            "{$d0} Dollar Value",

            "{$d1} Unit Cost",
            "{$d1} Stock Value",
            "{$d1} Qty in Machine",
            "{$d1} Qty in Warehouse",
            "{$d1} Stock Cost",
            "{$d1} Dollar Value",

            "{$d2} Unit Cost",
            "{$d2} Stock Value",
            "{$d2} Qty in Machine",
            "{$d2} Qty in Warehouse",
            "{$d2} Stock Cost",
            "{$d2} Dollar Value",
        ];

        $template = array_fill_keys($headers, null);

        // Helper to build a product row with all keys present
        $makeProductRow = function ($r) use ($template, $d0, $d1, $d2) {
            $row = $template;

            $row['Product ID']   = $r->product_code;
            $row['Product Name'] = $r->product_name;

            // d0
            $row["{$d0} Unit Cost"]       = (float) ($r->unit_cost_d0 ?? 0);
            $row["{$d0} Stock Value"]      = (float) ($r->stock_value_d0 ?? 0);
            $row["{$d0} Qty in Machine"]   = (int)   ($r->qty_vend_d0 ?? 0);
            $row["{$d0} Qty in Warehouse"] = (int)   ($r->qty_warehouse_d0 ?? 0);
            $row["{$d0} Stock Cost"]       = (float) ($r->stock_cost_d0 ?? 0);
            // Dollar Value left null for product rows

            // d1
            $row["{$d1} Unit Cost"]       = (float) ($r->unit_cost_d1 ?? 0);
            $row["{$d1} Stock Value"]      = (float) ($r->stock_value_d1 ?? 0);
            $row["{$d1} Qty in Machine"]   = (int)   ($r->qty_vend_d1 ?? 0);
            $row["{$d1} Qty in Warehouse"] = (int)   ($r->qty_warehouse_d1 ?? 0);
            $row["{$d1} Stock Cost"]       = (float) ($r->stock_cost_d1 ?? 0);

            // d2
            $row["{$d2} Unit Cost"]       = (float) ($r->unit_cost_d2 ?? 0);
            $row["{$d2} Stock Value"]      = (float) ($r->stock_value_d2 ?? 0);
            $row["{$d2} Qty in Machine"]   = (int)   ($r->qty_vend_d2 ?? 0);
            $row["{$d2} Qty in Warehouse"] = (int)   ($r->qty_warehouse_d2 ?? 0);
            $row["{$d2} Stock Cost"]       = (float) ($r->stock_cost_d2 ?? 0);

            return $row;
        };

        // Build product rows
        $exportRows = $rows->map($makeProductRow);

        // Helper to build KPI rows with only "Dollar Value" filled
        $makeKpiRow = function (string $label, $d0Val, $d1Val, $d2Val) use ($template, $d0, $d1, $d2) {
            $row = $template;
            $row['Product ID']   = null;
            $row['Product Name'] = $label;

            $row["{$d0} Dollar Value"] = (float) ($d0Val ?? 0);
            $row["{$d1} Dollar Value"] = (float) ($d1Val ?? 0);
            $row["{$d2} Dollar Value"] = (float) ($d2Val ?? 0);
            return $row;
        };

        // Append the 3 KPI rows (values already RM from getStockCountPivot3dQuery)
        $exportRows->push($makeKpiRow(
            'Receivable - Daily cash sales',
            $totals->cash_sales_amount_d0 ?? 0,
            $totals->cash_sales_amount_d1 ?? 0,
            $totals->cash_sales_amount_d2 ?? 0,
        ));

        $exportRows->push($makeKpiRow(
            'Receivable - Daily cashless sales',
            $totals->cashless_sales_amount_d0 ?? 0,
            $totals->cashless_sales_amount_d1 ?? 0,
            $totals->cashless_sales_amount_d2 ?? 0,
        ));

        $exportRows->push($makeKpiRow(
            'Coin Float in machines',
            $totals->coin_float_amount_d0 ?? 0,
            $totals->coin_float_amount_d1 ?? 0,
            $totals->coin_float_amount_d2 ?? 0,
        ));

        // --- Stream the file ---
        return (new FastExcel($this->yieldOneByOne($exportRows)))
            ->download('Stock_Count_'.Carbon::now()->format('Ymd_His').'.xlsx', fn ($row) => $row);
    }

    public function exportUnitCostProductExcel(Request $request)
    {
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = isset($request->sortBy) ? $request->sortBy : false;

        $products = $this->getUnitCostByProductQuery($request)->get();

        return (new FastExcel($this->yieldOneByOne($products)))->download('UnitCostByProduct_'.Carbon::now()->toDateTimeString().'.xlsx', function ($product) {
            return [
                'ID' => $product->code,
                'Name' => $product->name,
                'Sales# (thisMth)' => $product->this_month_count,
                'Sales$ (thisMth)' => $product->this_month_revenue/ 100,
                'GP (thisMth)' => $product->this_month_gross_profit/ 100,
                'GM (thisMth)' => $product->this_month_gross_profit_margin,
                'Sales# (lastMth)' => $product->last_month_count,
                'Sales$ (lastMth)' => $product->last_month_revenue/ 100,
                'GP (lastMth)' => $product->last_month_gross_profit/ 100,
                'GM (lastMth)' => $product->last_month_gross_profit_margin,
                'Sales# (last2Mth)' => $product->last_two_month_count,
                'Sales$ (last2Mth)' => $product->last_two_month_revenue/ 100,
                'GP (last2Mth)' => $product->last_two_month_gross_profit/ 100,
                'GM (last2Mth)' => $product->last_two_month_gross_profit_margin,
            ];
        });
    }

    public function exportUnitCostCategoryExcel(Request $request)
    {
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = isset($request->sortBy) ? $request->sortBy : false;

        $categories = $this->getUnitCostByCategoryQuery($request)->get();

        return (new FastExcel($this->yieldOneByOne($categories)))->download('UnitCostByCategory_'.Carbon::now()->toDateTimeString().'.xlsx', function ($category) {
            return [
                'Name' => $category->name,
                'Sales# (thisMth)' => $category->this_month_count,
                'Sales$ (thisMth)' => $category->this_month_revenue/ 100,
                'GP (thisMth)' => $category->this_month_gross_profit/ 100,
                'GM (thisMth)' => $category->this_month_gross_profit_margin,
                'Sales# (lastMth)' => $category->last_month_count,
                'Sales$ (lastMth)' => $category->last_month_revenue/ 100,
                'GP (lastMth)' => $category->last_month_gross_profit/ 100,
                'GM (lastMth)' => $category->last_month_gross_profit_margin,
                'Sales# (last2Mth)' => $category->last_two_month_count,
                'Sales$ (last2Mth)' => $category->last_two_month_revenue/ 100,
                'GP (last2Mth)' => $category->last_two_month_gross_profit/ 100,
                'GM (last2Mth)' => $category->last_two_month_gross_profit_margin,
            ];
        });
    }


    public function exportUnitCostLocationTypeExcel(Request $request)
    {
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = isset($request->sortBy) ? $request->sortBy : false;

        $locationTypes = $this->getUnitCostByLocationTypeQuery($request)->get();

        return (new FastExcel($this->yieldOneByOne($locationTypes)))->download('UnitCostByLocationType_'.Carbon::now()->toDateTimeString().'.xlsx', function ($locationType) {
            return [
                'Name' => $locationType->name,
                'Sales# (thisMth)' => $locationType->this_month_count,
                'Sales$ (thisMth)' => $locationType->this_month_revenue/ 100,
                'GP (thisMth)' => $locationType->this_month_gross_profit/ 100,
                'GM (thisMth)' => $locationType->this_month_gross_profit_margin,
                'Sales# (lastMth)' => $locationType->last_month_count,
                'Sales$ (lastMth)' => $locationType->last_month_revenue/ 100,
                'GP (lastMth)' => $locationType->last_month_gross_profit/ 100,
                'GM (lastMth)' => $locationType->last_month_gross_profit_margin,
                'Sales# (last2Mth)' => $locationType->last_two_month_count,
                'Sales$ (last2Mth)' => $locationType->last_two_month_revenue/ 100,
                'GP (last2Mth)' => $locationType->last_two_month_gross_profit/ 100,
                'GM (last2Mth)' => $locationType->last_two_month_gross_profit_margin,
            ];
        });
    }

    public function exportSnapshotChannelExcel(Request $request)
    {
        $request->merge(['currentMonth' => isset($request->currentMonth) ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone())]);
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : false)]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'month_number';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;

        $vendSnapshots = $this->getSnapshotQuery($request);
        $vendSnapshots = $vendSnapshots->get();
        $vendChannelsArr = [];
        foreach ($vendSnapshots as $vendSnapshot) {
            if($vendSnapshot->vend_channels_json) {
                foreach(json_decode($vendSnapshot->vend_channels_json) as $channel) {
                    if($channel->is_active == 1) {
                        array_push($vendChannelsArr, [
                            'vend_code' => $vendSnapshot->vend_code,
                            'full_name' => $vendSnapshot->customer_code ?
                                $vendSnapshot->customer_code.' '.$vendSnapshot->customer_name :
                                $vendSnapshot->vend_name,
                            'channel_code' => $channel->code,
                            'product_code' => $channel->product ? $channel->product->code : '',
                            'product_name' => $channel->product ? $channel->product->name : '',
                            'qty' => $channel->qty,
                            'capacity' => $channel->capacity,
                            'price' => $channel->amount/ 100,
                            'unit_cost' => $channel->product ? (UnitCost::where('product_id', $channel->product->id)->first() ? UnitCost::where('product_id', $channel->product->id)->first()->cost : 0) : '',
                            'balance_percent' => $channel->capacity ? round($channel->qty/ $channel->capacity * 100) : '',
                        ]);
                    }
                }
            }
        }

        return (new FastExcel($this->yieldOneByOne($vendChannelsArr)))->download('Vend_channels_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendChannel) {
            return [
                'Machine ID' => $vendChannel['vend_code'],
                'Customer Name' => $vendChannel['full_name'],
                'Channel' => $vendChannel['channel_code'],
                'Product Code' => $vendChannel['product_code'],
                'Product Name' => $vendChannel['product_name'],
                'Qty' => $vendChannel['qty'],
                'Capacity' => $vendChannel['capacity'],
                'Price' => $vendChannel['price'],
                'Unit Cost' => $vendChannel['unit_cost'],
                'Balance Percent(%)' => $vendChannel['balance_percent'],
            ];
        });
    }

    public function exportSalesExcel(Request $request, $type)
    {
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : 'true')]);

        if($request->currentFilterDate) {
            if($request->currentFilterDate != '-1') {
                $request->merge(['date_from' => explode(',',$request->currentFilterDate)[0]]);
                $request->merge(['date_to' => explode(',',$request->currentFilterDate)[1]]);
            }
            if($request->currentFilterDate == '-1') {
                $request->merge(['date_from' => Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->toDateString()]);
                $request->merge(['date_to' => Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->toDateString()]);
            }
        }else {
            $request->merge(['date_from' => Carbon::today()->setTimezone($this->getUserTimezone())->toDateString()]);
            $request->merge(['date_to' => Carbon::today()->setTimezone($this->getUserTimezone())->toDateString()]);
        }

        $request->sortKey = $request->sortKey ? $request->sortKey : 'amount';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $categoryClassName = get_class(new Customer());
        $modelName = 'vends';

        switch($type) {
            case 'category':
                $modelName = 'categories';
                break;
            case 'location-type':
                $modelName = 'location_types';
                break;
            case 'product':
                $modelName = 'products';
                break;
            case 'operator':
                $modelName = 'operators';
                break;
            case 'vend':
                $modelName = 'vends';
                break;
            case 'customer':
                $modelName = 'customers';
                break;
        }

        $items = $this->getSalesQuery($request, $modelName);
        $items = $items->when($request->sortKey, function($query, $search) use ($request) {
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        })
        ->get();

        return (new FastExcel($this->yieldOneByOne($items)))->download('SalesReport_'.$type.'_'.Carbon::now()->toDateTimeString().'.xlsx', function ($item) {
            return [
                'ID' => isset($item->code) ? $item->code : null,
                'Name' => $item->name,
                'Count' => $item->count,
                'Amount' => $item->amount/ 100,
            ];
        });
    }

    private function getSalesQuery($request, $className)
    {
        $transactionsQuery = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('location_types', 'vend_transactions.location_type_id', '=', 'location_types.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->leftJoin('vend_contracts', 'vend_contracts.id', '=', 'vend_transactions.vend_contract_id')
            ->leftJoin('vend_models', 'vend_models.id', '=', 'vend_transactions.vend_model_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vend_transactions.vend_prefix_id')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->where(function($query) use ($request) {
                $query->where('vend_channel_errors.code', '=', 6)
                    ->orWhere('vend_channel_errors.code', '=', 0)
                    ->orWhereNull('vend_channel_errors.code')
                    ->orWhere('is_multiple', '=', true);
            })
            ->where('vend_transactions.created_at', '>=', Carbon::parse($request->date_from)->startOfDay())
            ->where('vend_transactions.created_at', '<=', Carbon::parse($request->date_to)->endOfDay());

        switch($className) {
            case 'categories':
                $transactionsQuery
                    ->selectRaw('categories.id as id')
                    ->selectRaw('categories.name as name');
                break;
            case 'location_types':
                $transactionsQuery
                    ->selectRaw('location_types.id as id')
                    ->selectRaw('location_types.name as name');
                break;
            case 'products':
                $transactionsQuery
                    ->selectRaw('products.id as id')
                    ->selectRaw('products.code as code')
                    ->selectRaw('products.name as name');
                break;
            case 'operators':
                $transactionsQuery
                    ->selectRaw('operators.id as id')
                    ->selectRaw('operators.code as code')
                    ->selectRaw('operators.name as name');
                break;
            case 'vends':
                $transactionsQuery
                    ->selectRaw('vends.id as id')
                    ->selectRaw('vends.code as code')
                    ->selectRaw('CASE WHEN customers.id THEN CONCAT(customers.virtual_customer_code," (", customers.virtual_customer_prefix,") - ", customers.name) ELSE vends.name END as name')
                    ->selectRaw('vend_models.name as vend_model_name')
                    ->selectRaw('location_types.name as location_type_name');
                break;
            case 'customers':
                $transactionsQuery
                    ->selectRaw('customers.id as id')
                    ->selectRaw('customers.id + 20000 as code')
                    ->selectRaw('CASE WHEN customers.person_id THEN CONCAT(customers.virtual_customer_code, " - ", customers.name) ELSE customers.name END as name')
                    ->selectRaw('vend_models.name as vend_model_name')
                    ->selectRaw('location_types.name as location_type_name');
                break;
        }

        $transactionsQuery = $transactionsQuery
            ->selectRaw('COUNT(*) AS count')
            ->selectRaw('SUM(amount) AS amount');


        $transactionsQuery = $this->filterVendTransactionReport($transactionsQuery, $request);
        $transactionsQuery = $this->filterOperatorVendTransactionDB($transactionsQuery);
        $transactionsQuery = $transactionsQuery->groupBy('id');

        return $transactionsQuery;
    }

    private function getStockCountDayGraph(Request $request)
    {
        $tz   = $this->getUserTimezone();
        $from = now($tz)->startOfMonth()->subMonth()->startOfDay();
        $to   = now($tz)->endOfDay();

        if ($request->filled('day_date_from')) $from = Carbon::parse($request->day_date_from, $tz)->startOfDay();
        if ($request->filled('day_date_to'))   $to   = Carbon::parse($request->day_date_to,   $tz)->endOfDay();

        $dateSql = "DATE(CONCAT(sc.year,'-',LPAD(sc.month,2,'0'),'-',LPAD(sc.day,2,'0')))";

        // ----- coin float (simple sum per day) -----
        $coin = DB::table('stock_counts as sc')
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) $q->whereIn('sc.operator_id', $ids);
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) $q->whereIn('sc.vend_prefix_id', $ids);
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all') $q->whereIn('sc.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes) ? array_values(array_filter(array_map('trim', explode(',', $codes)))) : (array)$codes;
                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v')->whereColumn('v.id', 'sc.vend_id');
                    if (count($codes) > 1) $sq->whereIn('v.code', $codes);
                    elseif (count($codes) === 1) $sq->where('v.code', 'LIKE', '%'.$codes[0].'%');
                });
            })
            ->whereBetween(DB::raw($dateSql), [$from->toDateString(), $to->toDateString()])
            ->groupBy(DB::raw($dateSql))
            ->selectRaw("$dateSql as d")
            ->selectRaw('SUM(sc.coin_float_amount) / 100.0 as coin_float_rm')
            ->pluck('coin_float_rm', 'd');

        // ----- STOCK VALUE & (pivot-style) STOCK COST per day -----
        // Build a per-product-per-day subquery that mimics getStockCountPivot3dQuery:
        //   machine_cost_cents = SUM(unit_cost * qty_vend)
        //   wh_qty = MAX(qty_warehouse), wh_uc = MAX(unit_cost)
        //   stock_cost_rm_per_product = (machine_cost_cents + wh_qty * wh_uc) / 100
        // Also carry stock_value_amount to sum later.
        $perProductPerDay = DB::table('stock_count_items as sci')
            ->join('stock_counts as sc', 'sc.id', '=', 'sci.stock_count_id')
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) $q->whereIn('sc.operator_id', $ids);
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) $q->whereIn('sc.vend_prefix_id', $ids);
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all') $q->whereIn('sc.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes) ? array_values(array_filter(array_map('trim', explode(',', $codes)))) : (array)$codes;
                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v')->whereColumn('v.id', 'sc.vend_id');
                    if (count($codes) > 1) $sq->whereIn('v.code', $codes);
                    elseif (count($codes) === 1) $sq->where('v.code', 'LIKE', '%'.$codes[0].'%');
                });
            })
            ->whereBetween(DB::raw($dateSql), [$from->toDateString(), $to->toDateString()])
            ->groupBy(DB::raw($dateSql), 'sci.product_id')
            ->selectRaw("$dateSql as d")
            ->selectRaw('SUM(sci.unit_cost_amount * sci.qty_vend)               as machine_cost_cents')
            ->selectRaw('MAX(sci.qty_warehouse)                                 as wh_qty')
            ->selectRaw('MAX(sci.unit_cost_amount)                              as wh_uc_cents')
            ->selectRaw('SUM(sci.stock_value_amount)                            as stock_value_cents');

        $rows = DB::query()
            ->fromSub($perProductPerDay, 'ppd')
            ->groupBy('d')
            ->selectRaw('d')
            // pivot-style stock cost (RM)
            ->selectRaw('SUM( (machine_cost_cents + wh_qty * wh_uc_cents) / 100.0 ) as stock_cost_rm')
            // stock value in machines (RM)
            ->selectRaw('SUM( stock_value_cents / 100.0 ) as stock_value_rm')
            ->get()
            ->keyBy('d');

        // ----- assemble continuous daily series -----
        $series = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $d   = $cursor->toDateString();
            $y   = (int)$cursor->year;
            $m   = (int)$cursor->month;
            $day = (int)$cursor->day;

            $row = $rows->get($d);
            $series[] = (object) [
                'amount'     => $row?->stock_value_rm ?? 0.0, // y (left): Stock Value in Machines (RM)
                'count'      => $row?->stock_cost_rm  ?? 0.0, // y1 (right): Total Stock Cost - before GST (RM)
                'coin_float' => $coin[$d]            ?? 0.0,

                'date'       => $d,
                'day'        => $day,
                'month'      => $m,
                'month_name' => Carbon::createFromDate($y, $m, 1)->format('F'),
                'year'       => $y,
            ];

            $cursor->addDay();
        }

        usort($series, fn($a,$b) => strcmp($a->date, $b->date));
        return collect($series);
    }

    private function getStockCountQtyDayGraph(Request $request)
    {
        $tz   = $this->getUserTimezone();
        $from = now($tz)->startOfMonth()->subMonth()->startOfDay();
        $to   = now($tz)->endOfDay();

        if ($request->filled('day_date_from')) $from = Carbon::parse($request->day_date_from, $tz)->startOfDay();
        if ($request->filled('day_date_to'))   $to   = Carbon::parse($request->day_date_to,   $tz)->endOfDay();

        $dateSql = "DATE(CONCAT(sc.year,'-',LPAD(sc.month,2,'0'),'-',LPAD(sc.day,2,'0')))";

        // Per product, per day:
        // - machine_qty  = SUM(qty_vend)
        // - warehouse_qty = MAX(qty_warehouse) (to avoid multiplying counts across rows)
        $perProductPerDay = DB::table('stock_count_items as sci')
            ->join('stock_counts as sc', 'sc.id', '=', 'sci.stock_count_id')
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) $q->whereIn('sc.operator_id', $ids);
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) $q->whereIn('sc.vend_prefix_id', $ids);
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all') $q->whereIn('sc.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes) ? array_values(array_filter(array_map('trim', explode(',', $codes)))) : (array)$codes;
                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v')->whereColumn('v.id', 'sc.vend_id');
                    if (count($codes) > 1) $sq->whereIn('v.code', $codes);
                    elseif (count($codes) === 1) $sq->where('v.code', 'LIKE', '%'.$codes[0].'%');
                });
            })
            ->whereBetween(DB::raw($dateSql), [$from->toDateString(), $to->toDateString()])
            ->groupBy(DB::raw($dateSql), 'sci.product_id')
            ->selectRaw("$dateSql as d")
            ->selectRaw('SUM(sci.qty_vend)     as machine_qty')
            ->selectRaw('MAX(sci.qty_warehouse) as warehouse_qty');

        // Aggregate to a single row per day
        $rows = DB::query()
            ->fromSub($perProductPerDay, 'ppd')
            ->groupBy('d')
            ->selectRaw('d')
            ->selectRaw('SUM(machine_qty)   as machine_qty')
            ->selectRaw('SUM(warehouse_qty) as warehouse_qty')
            ->get()
            ->keyBy('d');

        // Build continuous daily series
        $series = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $d   = $cursor->toDateString();
            $y   = (int)$cursor->year;
            $m   = (int)$cursor->month;
            $day = (int)$cursor->day;

            $row = $rows->get($d);
            $series[] = (object)[
                'date'          => $d,
                'day'           => $day,
                'month'         => $m,
                'month_name'    => Carbon::createFromDate($y, $m, 1)->format('F'),
                'year'          => $y,
                'machine_qty'   => $row?->machine_qty   ?? 0,
                'warehouse_qty' => $row?->warehouse_qty ?? 0,
            ];

            $cursor->addDay();
        }

        usort($series, fn($a,$b) => strcmp($a->date, $b->date));
        return collect($series);
    }


    private function getUnitCostByVendQuery($request)
    {
        $currentDate = $request->currentMonth
        ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())
        : Carbon::today()->setTimezone($this->getUserTimezone());

        $queryVendTransactions = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->where('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth()->startOfDay())
            ->where('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth()->endOfDay())
            ->whereColumn('qty', 'success_qty');

        $queryVendTransactions = $this->filterVendTransactionReport($queryVendTransactions, $request);
        $queryVendTransactions = $this->filterOperatorVendTransactionDB($queryVendTransactions);

        $currentMonthFormatted = $currentDate->format('Y-m');

        // dd($queryVendTransactions->get()->toArray());

        $queryVendTransactions = $queryVendTransactions
            ->select(
                'vends.id',
                'customers.id AS customer_id',
                DB::raw('CASE WHEN customers.person_id THEN CONCAT(customers.virtual_customer_code," (", customers.virtual_customer_prefix,")") ELSE vends.code END as customer_code'),
                'customers.name AS customer_name',
                'vends.name',
                'vends.code',
                DB::raw('PERIOD_DIFF(DATE_FORMAT("' . $currentMonthFormatted . '-01", "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) AS month_diff'),
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100 / SUM(revenue), 1) AS gross_profit_margin'),
                DB::raw('SUM(CASE WHEN PERIOD_DIFF(DATE_FORMAT("' . $currentMonthFormatted . '-01", "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) = 0 THEN revenue ELSE 0 END) AS this_month_revenue')
            )
            ->groupBy('vends.id', 'month_diff');

            // dd($queryVendTransactions->toSql());

        $vends = DB::query()
            ->fromSub($queryVendTransactions, 'transac')
            ->select(
                'customer_code',
                'customer_name',
                'id',
                'name',
                'code',
                'month_diff',
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN count ELSE 0 END) AS this_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit ELSE 0 END) AS this_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit_margin ELSE 0 END) AS this_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN count ELSE 0 END) AS last_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN revenue ELSE 0 END) AS last_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit ELSE 0 END) AS last_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit_margin ELSE 0 END) AS last_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN count ELSE 0 END) AS last_two_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN revenue ELSE 0 END) AS last_two_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit ELSE 0 END) AS last_two_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit_margin ELSE 0 END) AS last_two_month_gross_profit_margin'),
            )
            ->groupBy('id');

        $vends = $vends->when($request->sortKey, function($query, $search) use ($request) {
            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }else {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }
        });

        return $vends;
    }

    private function getUnitCostByProductQuery($request)
    {
        $currentDate = $request->currentMonth
        ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())
        : Carbon::today()->setTimezone($this->getUserTimezone());

        $currentMonthFormatted = $currentDate->format('Y-m');
        $rangeStart = $currentDate->copy()->subMonths(2)->startOfMonth()->startOfDay();
        $rangeEnd = $currentDate->copy()->endOfMonth()->endOfDay();

        $singleTransactions = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('vend_channels', 'vend_transactions.vend_channel_id', '=', 'vend_channels.id')
            ->leftJoin('products', function($join) {
                $join->on('products.id', '=', 'vend_transactions.product_id')
                    ->orOn('products.id', '=', 'vend_channels.product_id');
            })
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->whereBetween('vend_transactions.created_at', [$rangeStart, $rangeEnd])
            ->whereIn('vend_transaction_json->SErr', [0, 6])
            ->where(function($query) {
                $query->where('vend_transactions.is_multiple', false)
                    ->orWhereNotExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('vend_transaction_items')
                            ->whereColumn('vend_transaction_items.vend_transaction_id', 'vend_transactions.id');
                    });
            });

        $singleTransactions = $this->filterVendTransactionReport($singleTransactions, $request);
        $singleTransactions = $this->filterOperatorVendTransactionDB($singleTransactions);

        $singleRevenueExpression = 'COALESCE(vend_transactions.revenue, vend_transactions.amount, 0)';
        $singleUnitCostExpression = 'COALESCE(vend_transactions.unit_cost, 0)';
        $singleGrossProfitExpression = 'COALESCE(vend_transactions.gross_profit, (' . $singleRevenueExpression . ' - ' . $singleUnitCostExpression . '))';
        $singleCountExpression = 'CASE WHEN vend_transactions.success_qty IS NULL OR vend_transactions.success_qty = 0 THEN 1 ELSE vend_transactions.success_qty END';

        $singleAggregated = $singleTransactions
            ->whereNotNull('products.id')
            ->select(
                'products.id as id',
                'products.name',
                'products.code',
                DB::raw('PERIOD_DIFF(DATE_FORMAT("' . $currentMonthFormatted . '-01", "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) AS month_diff'),
                DB::raw('SUM(' . $singleCountExpression . ') AS count'),
                DB::raw('SUM(' . $singleRevenueExpression . ') AS revenue'),
                DB::raw('SUM(' . $singleGrossProfitExpression . ') AS gross_profit'),
                DB::raw('ROUND(SUM(' . $singleGrossProfitExpression . ') * 100 / NULLIF(SUM(' . $singleRevenueExpression . '), 0), 1) AS gross_profit_margin')
            )
            ->groupBy('products.id', 'month_diff');

        $multiTransactions = DB::table('vend_transaction_items')
            ->join('vend_transactions', 'vend_transaction_items.vend_transaction_id', '=', 'vend_transactions.id')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('vend_channels', 'vend_transaction_items.vend_channel_id', '=', 'vend_channels.id')
            ->leftJoin('products', function($join) {
                $join->on('products.id', '=', 'vend_transaction_items.product_id')
                    ->orOn('products.id', '=', 'vend_channels.product_id');
            })
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->whereBetween('vend_transactions.created_at', [$rangeStart, $rangeEnd])
            ->whereIn('vend_transaction_items.vend_channel_error_code', [0, 6])
            ->where('vend_transactions.is_multiple', true);

        $multiTransactions = $this->filterVendTransactionReport($multiTransactions, $request);
        $multiTransactions = $this->filterOperatorVendTransactionDB($multiTransactions);

        $multiRevenueExpression = 'COALESCE(
                vend_channels.amount,
                ROUND(
                    CASE
                        WHEN vend_transactions.success_qty IS NOT NULL AND vend_transactions.success_qty > 0 THEN ' . $singleRevenueExpression . ' / NULLIF(vend_transactions.success_qty, 0)
                        WHEN vend_transactions.qty IS NOT NULL AND vend_transactions.qty > 0 THEN ' . $singleRevenueExpression . ' / NULLIF(vend_transactions.qty, 0)
                        ELSE 0
                    END
                ),
                0
            )';
        $multiUnitCostExpression = 'ROUND(COALESCE(vend_transaction_items.unit_cost, 0) * 100)';
        $multiGrossProfitExpression = '(' . $multiRevenueExpression . ' - ' . $multiUnitCostExpression . ')';
        $multiAggregated = $multiTransactions
            ->whereNotNull('products.id')
            ->select(
                'products.id as id',
                'products.name',
                'products.code',
                DB::raw('PERIOD_DIFF(DATE_FORMAT("' . $currentMonthFormatted . '-01", "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) AS month_diff'),
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(' . $multiRevenueExpression . ') AS revenue'),
                DB::raw('SUM(' . $multiGrossProfitExpression . ') AS gross_profit'),
                DB::raw('ROUND(SUM(' . $multiGrossProfitExpression . ') * 100 / NULLIF(SUM(' . $multiRevenueExpression . '), 0), 1) AS gross_profit_margin')
            )
            ->groupBy('products.id', 'month_diff');

        $combinedTransactions = $singleAggregated->unionAll($multiAggregated);

        $products = DB::query()
            ->fromSub($combinedTransactions, 'transac')
            ->select(
                'id',
                'name',
                'code',
                'month_diff',
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN count ELSE 0 END) AS this_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit ELSE 0 END) AS this_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit_margin ELSE 0 END) AS this_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN count ELSE 0 END) AS last_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN revenue ELSE 0 END) AS last_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit ELSE 0 END) AS last_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit_margin ELSE 0 END) AS last_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN count ELSE 0 END) AS last_two_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN revenue ELSE 0 END) AS last_two_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit ELSE 0 END) AS last_two_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit_margin ELSE 0 END) AS last_two_month_gross_profit_margin'),
            )
            ->groupBy('id');

        $products = $products->when($request->sortKey, function($query, $search) use ($request) {
            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }else {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }
        });

        return $products;
    }

    private function getUnitCostByCategoryQuery($request)
    {
        $currentDate = $request->currentMonth
        ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())
        : Carbon::today()->setTimezone($this->getUserTimezone());

        $className = get_class(new Customer());

        $currentMonthFormatted = $currentDate->format('Y-m');

        $queryVendTransactions = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->where('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth()->startOfDay())
            ->where('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth()->endOfDay())
            ->whereIn('vend_transaction_json->SErr', [0, 6]);

        $queryVendTransactions = $this->filterVendTransactionReport($queryVendTransactions, $request);
        $queryVendTransactions = $this->filterOperatorVendTransactionDB($queryVendTransactions);

        $queryVendTransactions = $queryVendTransactions
            ->select(
                'categories.id',
                'categories.name',
                'categories.classname',
                DB::raw('PERIOD_DIFF(DATE_FORMAT("' . $currentMonthFormatted . '-01", "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) AS month_diff'),
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100 / SUM(revenue), 1) AS gross_profit_margin'),
                DB::raw('SUM(CASE WHEN PERIOD_DIFF(DATE_FORMAT("' . $currentMonthFormatted . '-01", "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) = 0 THEN revenue ELSE 0 END) AS this_month_revenue')
            )
            ->groupBy('categories.id', 'month_diff');


        $categories = DB::query()
            ->fromSub($queryVendTransactions, 'transac')
            ->select(
                'id',
                'name',
                'month_diff',
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN count ELSE 0 END) AS this_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit ELSE 0 END) AS this_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit_margin ELSE 0 END) AS this_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN count ELSE 0 END) AS last_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN revenue ELSE 0 END) AS last_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit ELSE 0 END) AS last_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit_margin ELSE 0 END) AS last_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN count ELSE 0 END) AS last_two_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN revenue ELSE 0 END) AS last_two_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit ELSE 0 END) AS last_two_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit_margin ELSE 0 END) AS last_two_month_gross_profit_margin'),
            )
            ->where('classname', $className)
            ->groupBy('id');

        $categories = $categories->when($request->sortKey, function($query, $search) use ($request) {
            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }else {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }
        });

        return $categories;
    }

    private function getUnitCostByLocationTypeQuery($request)
    {
        $currentDate = $request->currentMonth
        ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())
        : Carbon::today()->setTimezone($this->getUserTimezone());

        $currentMonthFormatted = $currentDate->format('Y-m');

        $queryVendTransactions = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('location_types', 'customers.location_type_id', '=', 'location_types.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->where('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth()->startOfDay())
            ->where('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth()->endOfDay())
            ->whereIn('vend_transaction_json->SErr', [0, 6]);

        $queryVendTransactions = $this->filterVendTransactionReport($queryVendTransactions, $request);
        $queryVendTransactions = $this->filterOperatorVendTransactionDB($queryVendTransactions);

        $queryVendTransactions = $queryVendTransactions
            ->select(
                'location_types.id',
                'location_types.name',
                DB::raw('PERIOD_DIFF(DATE_FORMAT("' . $currentMonthFormatted . '-01", "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) AS month_diff'),
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100 / SUM(revenue), 1) AS gross_profit_margin'),
                DB::raw('SUM(CASE WHEN PERIOD_DIFF(DATE_FORMAT("' . $currentMonthFormatted . '-01", "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) = 0 THEN revenue ELSE 0 END) AS this_month_revenue')
            )
            ->groupBy('location_types.id', 'month_diff');


        $locationTypes = DB::query()
            ->fromSub($queryVendTransactions, 'transac')
            ->select(
                'id',
                'name',
                'month_diff',
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN count ELSE 0 END) AS this_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit ELSE 0 END) AS this_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit_margin ELSE 0 END) AS this_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN count ELSE 0 END) AS last_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN revenue ELSE 0 END) AS last_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit ELSE 0 END) AS last_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit_margin ELSE 0 END) AS last_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN count ELSE 0 END) AS last_two_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN revenue ELSE 0 END) AS last_two_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit ELSE 0 END) AS last_two_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit_margin ELSE 0 END) AS last_two_month_gross_profit_margin'),
            )
            ->groupBy('id');

        $locationTypes = $locationTypes->when($request->sortKey, function($query, $search) use ($request) {
            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }else {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }
        });

        return $locationTypes;
    }

    private function getSnapshotQuery($request)
    {
        $vendSnapshots = DB::table('vend_snapshots')
            ->leftJoin('vends', 'vends.id', '=', 'vend_snapshots.vend_id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_snapshots.customer_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_snapshots.operator_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->select(
                'vend_snapshots.id AS id',
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'customers.person_id',
                'customers.virtual_customer_code',
                'customers.virtual_customer_prefix',
                DB::raw('MONTH(vend_snapshots.created_at) - 1 AS month_number'),
                DB::raw('YEAR(vend_snapshots.created_at) AS year_number'),
                'product_mappings.name AS product_mapping_name',
                'vends.code AS vend_code',
                'vends.name AS vend_name',
                'vend_snapshots.created_at AS created_at',
                'vend_snapshots.parameter_json',
                'vend_snapshots.vend_channels_json',
            );

        $vendSnapshots = $this->filterOperatorVendTransactionDB($vendSnapshots);
        $vendSnapshots = $this->filterVendsDB($vendSnapshots, $request);


        $vendSnapshots = $vendSnapshots
            ->when($request->currentMonth, function($query, $search) {
                $query
                    ->where('vend_snapshots.created_at', '>=', $search->copy()->startOfMonth()->addDay()->startOfDay())
                    ->where('vend_snapshots.created_at', '<=', $search->copy()->endOfMonth()->addDay()->endOfDay());
            });
        $vendSnapshots = $vendSnapshots->groupBy('vends.id', 'year_number', 'month_number');

        return $vendSnapshots;
    }

    private function getStockCountQuery($request)
    {
        // read sort inputs (sortBy=true => DESC, false => ASC)
        $sortKey  = $request->input('sortKey');                // 'sequence' | 'channel_code' | null
        $sortDesc = filter_var($request->input('sortBy'), FILTER_VALIDATE_BOOLEAN); // bool
        $dir      = $sortDesc ? 'DESC' : 'ASC';

        if(!$sortKey) {
            // default to sequence if not specified
            $sortKey = 'product_code';
        }

        $stockCounts = StockCount::query()
            ->with([
                'stockCountItems' => function ($q) use ($sortKey, $dir) {
                    if($sortKey === 'stock_value_amount') {
                        $q->orderBy('stock_value_amount', $dir);
                    }else if($sortKey === 'qty_vend') {
                        $q->orderBy('qty_vend', $dir);
                    }else if($sortKey === 'qty_warehouse') {
                        $q->orderBy('qty_warehouse', $dir);
                    }else if($sortKey === 'stock_cost_amount') {
                        $q->orderBy('stock_cost_amount', $dir);
                    }
                },
                'stockCountItems.product' => function ($q) use ($sortKey, $dir) {
                    if ($sortKey === 'product_code') {
                        // nulls last, then sequence asc/desc, then channel_code as tiebreaker
                        $q->orderByRaw("CAST(code AS UNSIGNED) $dir")
                          ->orderBy('code', $dir);

                    }
                },
                'stockCountItems.product.thumbnail'
            ])
            ->filterIndex($request)
            ->groupBy();

        return $stockCounts;
    }

    private function getStockCountPivot3dQuery(Request $request)
    {
        $tz  = $this->getUserTimezone();
        $end = Carbon::parse($request->date_to ?? Carbon::today($tz), $tz)->toDateString();
        $d0  = Carbon::parse($end)->subDay()->toDateString();
        $d1  = Carbon::parse($end)->subDays(2)->toDateString();
        $d2  = Carbon::parse($end)->subDays(3)->toDateString();

        $dateSql = "DATE(CONCAT(sc.year,'-',LPAD(sc.month,2,'0'),'-',LPAD(sc.day,2,'0')))";

        // ---------- rows (per product, 3 days) ----------
        $q = DB::table('stock_count_items as sci')
            ->join('stock_counts as sc', 'sc.id', '=', 'sci.stock_count_id')
            ->join('products as p', 'p.id', '=', 'sci.product_id')

            // filters
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereIn('sc.operator_id', $ids);
                }
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereIn('sc.vend_prefix_id', $ids);
                }
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all') $q->whereIn('sc.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes)
                    ? array_values(array_filter(array_map('trim', explode(',', $codes))))
                    : (array) $codes;

                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v')->whereColumn('v.id', 'sc.vend_id');
                    if (count($codes) > 1) $sq->whereIn('v.code', $codes);
                    elseif (count($codes) === 1) $sq->where('v.code', 'LIKE', '%'.$codes[0].'%');
                });
            })
            ->when($request->products, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereIn('sci.product_id', $ids);
                }
            })

            ->whereIn(DB::raw($dateSql), [$d0, $d1, $d2])

            ->select([
                'p.id   as product_id',
                'p.code as product_code',
                'p.name as product_name',

                // qty in machine (sum) + qty in warehouse (once)
                DB::raw("SUM(CASE WHEN {$dateSql} = '{$d0}' THEN sci.qty_vend ELSE 0 END) AS qty_vend_d0"),
                DB::raw("SUM(CASE WHEN {$dateSql} = '{$d1}' THEN sci.qty_vend ELSE 0 END) AS qty_vend_d1"),
                DB::raw("SUM(CASE WHEN {$dateSql} = '{$d2}' THEN sci.qty_vend ELSE 0 END) AS qty_vend_d2"),

                DB::raw("MAX(CASE WHEN {$dateSql} = '{$d0}' THEN sci.qty_warehouse ELSE 0 END) AS qty_warehouse_d0"),
                DB::raw("MAX(CASE WHEN {$dateSql} = '{$d1}' THEN sci.qty_warehouse ELSE 0 END) AS qty_warehouse_d1"),
                DB::raw("MAX(CASE WHEN {$dateSql} = '{$d2}' THEN sci.qty_warehouse ELSE 0 END) AS qty_warehouse_d2"),

                // unit cost (RM) per day, directly from sci
                DB::raw("ROUND(MAX(CASE WHEN {$dateSql} = '{$d0}' THEN sci.unit_cost_amount ELSE 0 END) / 100, 2) AS unit_cost_d0"),
                DB::raw("ROUND(MAX(CASE WHEN {$dateSql} = '{$d1}' THEN sci.unit_cost_amount ELSE 0 END) / 100, 2) AS unit_cost_d1"),
                DB::raw("ROUND(MAX(CASE WHEN {$dateSql} = '{$d2}' THEN sci.unit_cost_amount ELSE 0 END) / 100, 2) AS unit_cost_d2"),

                // stock value in machine (RM)
                DB::raw("ROUND(SUM(CASE WHEN {$dateSql} = '{$d0}' THEN sci.stock_value_amount ELSE 0 END) / 100, 2) AS stock_value_d0"),
                DB::raw("ROUND(SUM(CASE WHEN {$dateSql} = '{$d1}' THEN sci.stock_value_amount ELSE 0 END) / 100, 2) AS stock_value_d1"),
                DB::raw("ROUND(SUM(CASE WHEN {$dateSql} = '{$d2}' THEN sci.stock_value_amount ELSE 0 END) / 100, 2) AS stock_value_d2"),

                // stock cost (RM) = machine cost + ONE warehouse cost
                DB::raw("
                    ROUND((
                        SUM(CASE WHEN {$dateSql} = '{$d0}' THEN (sci.unit_cost_amount * sci.qty_vend) ELSE 0 END)
                        + (MAX(CASE WHEN {$dateSql} = '{$d0}' THEN sci.qty_warehouse ELSE 0 END)
                           * MAX(CASE WHEN {$dateSql} = '{$d0}' THEN sci.unit_cost_amount ELSE 0 END))
                    ) / 100, 2) AS stock_cost_d0
                "),
                DB::raw("
                    ROUND((
                        SUM(CASE WHEN {$dateSql} = '{$d1}' THEN (sci.unit_cost_amount * sci.qty_vend) ELSE 0 END)
                        + (MAX(CASE WHEN {$dateSql} = '{$d1}' THEN sci.qty_warehouse ELSE 0 END)
                           * MAX(CASE WHEN {$dateSql} = '{$d1}' THEN sci.unit_cost_amount ELSE 0 END))
                    ) / 100, 2) AS stock_cost_d1
                "),
                DB::raw("
                    ROUND((
                        SUM(CASE WHEN {$dateSql} = '{$d2}' THEN (sci.unit_cost_amount * sci.qty_vend) ELSE 0 END)
                        + (MAX(CASE WHEN {$dateSql} = '{$d2}' THEN sci.qty_warehouse ELSE 0 END)
                           * MAX(CASE WHEN {$dateSql} = '{$d2}' THEN sci.unit_cost_amount ELSE 0 END))
                    ) / 100, 2) AS stock_cost_d2
                "),
            ])
            ->groupBy('p.id', 'p.code', 'p.name');

        // sorting
        $sortKey = $request->input('sortKey', 'product_code');
        $desc    = filter_var($request->input('sortBy', false), FILTER_VALIDATE_BOOLEAN);
        $dir     = $desc ? 'desc' : 'asc';
        $allowed = [
            'product_code',
            'unit_cost_d0','unit_cost_d1','unit_cost_d2',
            'qty_vend_d0','qty_vend_d1','qty_vend_d2',
            'qty_warehouse_d0','qty_warehouse_d1','qty_warehouse_d2',
            'stock_value_d0','stock_value_d1','stock_value_d2',
            'stock_cost_d0','stock_cost_d1','stock_cost_d2'
        ];
        if (!in_array($sortKey, $allowed, true)) $sortKey = 'product_code';
        if ($sortKey === 'product_code') {
            $q->orderByRaw('CAST(product_code AS UNSIGNED) '.$dir)->orderBy('product_code', $dir);
        } else {
            $q->orderBy($sortKey, $dir);
        }

        // ---------- totals from the row aliases ----------
        $totals = DB::query()
            ->fromSub((clone $q)->reorder(), 'rows')
            ->selectRaw('
                /* sums shown in footer */
                SUM(stock_value_d0)   AS stock_value_d0,
                SUM(qty_vend_d0)      AS qty_vend_d0,
                SUM(qty_warehouse_d0) AS qty_warehouse_d0,
                SUM(stock_cost_d0)    AS stock_cost_d0,

                SUM(stock_value_d1)   AS stock_value_d1,
                SUM(qty_vend_d1)      AS qty_vend_d1,
                SUM(qty_warehouse_d1) AS qty_warehouse_d1,
                SUM(stock_cost_d1)    AS stock_cost_d1,

                SUM(stock_value_d2)   AS stock_value_d2,
                SUM(qty_vend_d2)      AS qty_vend_d2,
                SUM(qty_warehouse_d2) AS qty_warehouse_d2,
                SUM(stock_cost_d2)    AS stock_cost_d2,

                /* weighted average unit cost per day = total_cost / total_qty */
                SUM(unit_cost_d0) AS unit_cost_d0,
                SUM(unit_cost_d1) AS unit_cost_d1,
                SUM(unit_cost_d2) AS unit_cost_d2
            ')
            ->first();

        // ---------- KPIs (cash/cashless/coin) ----------
        $kpis = DB::table('stock_counts as sc')
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereIn('sc.operator_id', $ids);
                }
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereIn('sc.vend_prefix_id', $ids);
                }
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all') $q->whereIn('sc.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes)
                    ? array_values(array_filter(array_map('trim', explode(',', $codes))))
                    : (array) $codes;

                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v')->whereColumn('v.id', 'sc.vend_id');
                    if (count($codes) > 1) $sq->whereIn('v.code', $codes);
                    elseif (count($codes) === 1) $sq->where('v.code', 'LIKE', '%'.$codes[0].'%');
                });
            })
            ->whereIn(DB::raw($dateSql), [$d0, $d1, $d2])
            ->selectRaw("
                ROUND(SUM(CASE WHEN {$dateSql} = '{$d0}' THEN sc.cash_sales_amount     ELSE 0 END) / 100, 2) AS cash_sales_amount_d0,
                ROUND(SUM(CASE WHEN {$dateSql} = '{$d1}' THEN sc.cash_sales_amount     ELSE 0 END) / 100, 2) AS cash_sales_amount_d1,
                ROUND(SUM(CASE WHEN {$dateSql} = '{$d2}' THEN sc.cash_sales_amount     ELSE 0 END) / 100, 2) AS cash_sales_amount_d2,

                ROUND(SUM(CASE WHEN {$dateSql} = '{$d0}' THEN sc.cashless_sales_amount ELSE 0 END) / 100, 2) AS cashless_sales_amount_d0,
                ROUND(SUM(CASE WHEN {$dateSql} = '{$d1}' THEN sc.cashless_sales_amount ELSE 0 END) / 100, 2) AS cashless_sales_amount_d1,
                ROUND(SUM(CASE WHEN {$dateSql} = '{$d2}' THEN sc.cashless_sales_amount ELSE 0 END) / 100, 2) AS cashless_sales_amount_d2,

                ROUND(SUM(CASE WHEN {$dateSql} = '{$d0}' THEN sc.coin_float_amount     ELSE 0 END) / 100, 2) AS coin_float_amount_d0,
                ROUND(SUM(CASE WHEN {$dateSql} = '{$d1}' THEN sc.coin_float_amount     ELSE 0 END) / 100, 2) AS coin_float_amount_d1,
                ROUND(SUM(CASE WHEN {$dateSql} = '{$d2}' THEN sc.coin_float_amount     ELSE 0 END) / 100, 2) AS coin_float_amount_d2
            ")
            ->first();

        // merge KPI fields + pre-compute "Dollar Value" (cash+cashless+coin) for the footer
        foreach ((array) $kpis as $k => $v) {
            $totals->{$k} = $v ?? 0;
        }
        $totals->dollar_value_d0 = ($totals->cash_sales_amount_d0 ?? 0)
                                 + ($totals->cashless_sales_amount_d0 ?? 0)
                                 + ($totals->coin_float_amount_d0 ?? 0);
        $totals->dollar_value_d1 = ($totals->cash_sales_amount_d1 ?? 0)
                                 + ($totals->cashless_sales_amount_d1 ?? 0)
                                 + ($totals->coin_float_amount_d1 ?? 0);
        $totals->dollar_value_d2 = ($totals->cash_sales_amount_d2 ?? 0)
                                 + ($totals->cashless_sales_amount_d2 ?? 0)
                                 + ($totals->coin_float_amount_d2 ?? 0);

        // pagination
        $perPage   = ($request->numberPerPage === 'All') ? 10000 : (int)($request->numberPerPage ?? 100);
        $paginator = $q->paginate($perPage)->appends($request->query());

        return [$paginator, ['d0' => $d0, 'd1' => $d1, 'd2' => $d2], $totals];
    }


    private function getSalesSubTotal($dataCols)
    {
        return collect((clone $dataCols)->get())->pipe(function($data) {
            $thisMonthTotal = $data->sum(function($data) {
                return $data->this_month_revenue/ 100;
            });
            $thisMonthGrossProfitTotal = $data->sum(function($data) {
                return $data->this_month_gross_profit/ 100;
            });
            $lastMonthTotal = $data->sum(function($data) {
                return $data->last_month_revenue/ 100;
            });
            $lastMonthGrossProfitTotal = $data->sum(function($data) {
                return $data->last_month_gross_profit/ 100;
            });
            $lastTwoMonthTotal = $data->sum(function($data) {
                return $data->last_two_month_revenue/ 100;
            });
            $lastTwoMonthGrossProfitTotal = $data->sum(function($data) {
                return $data->last_two_month_gross_profit/ 100;
            });
            return [
                'this_month_count_total' => $data->sum('this_month_count'),
                'this_month_revenue_total' => $thisMonthTotal,
                'this_month_gross_profit_total' => $thisMonthGrossProfitTotal,
                'this_month_gross_margin_total' => round($thisMonthGrossProfitTotal/($thisMonthTotal ? $thisMonthTotal : 1) * 100, 1),
                'last_month_count_total' => $data->sum('last_month_count'),
                'last_month_revenue_total' => $lastMonthTotal,
                'last_month_gross_profit_total' => $lastMonthGrossProfitTotal,
                'last_month_gross_margin_total' => round($lastMonthGrossProfitTotal/($lastMonthTotal ? $lastMonthTotal : 1) * 100, 1),
                'last_two_month_count_total' => $data->sum('last_two_month_count'),
                'last_two_month_revenue_total' => $lastTwoMonthTotal,
                'last_two_month_gross_profit_total' => $lastTwoMonthGrossProfitTotal,
                'last_two_month_gross_margin_total' => round($lastTwoMonthGrossProfitTotal/($lastTwoMonthTotal ? $lastTwoMonthTotal : 1) * 100, 1),
            ];
        });
    }

    private function getSalesReportTotals($items)
    {
        return collect((clone $items)->get())->pipe(function($item) {
            $total_count = $item->sum(function($item) {
                return $item->count;
            });
            $total_amount = $item->sum(function($item) {
                return $item->amount/ 100;
            });
            return [
                'total_count' => $total_count,
                'total_amount' => $total_amount,
            ];
        });
    }

    private function yieldOneByOne($items) {
        foreach($items as $item) {
            yield $item;
        }
    }
}
