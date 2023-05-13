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
use App\Http\Resources\VendDBResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendSnapshotDBResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Vend;
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
use Rap2hpoutre\FastExcel\FastExcel;

class ReportController extends Controller
{
    use HasFilter, HasMonthOption, GetUserTimezone;

    public function indexVm(Request $request)
    {
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

        return Inertia::render('Report/IndexVm', [
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
            'totals' => $totals,
            'vends' => VendDBResource::collection($vends),
        ]);
    }

    public function indexProduct(Request $request)
    {
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

        return Inertia::render('Report/IndexProduct', [
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
            'totals' => $totals,
            'products' => ProductDBResource::collection($products),
        ]);
    }

    public function indexCategory(Request $request)
    {
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $className = get_class(new Customer());

        $categories = $this->getUnitCostByCategoryQuery($request);
        $totals = $this->getSalesSubTotal($categories);
        $categories = $categories->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/IndexCategory', [
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
            'totals' => $totals,
            'categories' => CategoryDBResource::collection($categories),
        ]);
    }

    public function indexLocationType(Request $request)
    {
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

        return Inertia::render('Report/IndexLocationType', [
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
            'totals' => $totals,
            'locationTypes' => LocationTypeDBResource::collection($locationTypes),
        ]);
    }

    public function indexStockCount(Request $request)
    {
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'month_number';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $request->is_binded_customer = auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : false);
        $className = get_class(new Customer());

        $stockCounts = $this->getStockCountQuery($request);
        $stockCounts = $stockCounts->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/IndexStockCount', [
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
            'stockCounts' => VendSnapshotDBResource::collection($stockCounts),
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

    private function getUnitCostByVendQuery($request)
    {
        $currentDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone());

        $queryVendTransactions = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth()->toDateString())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth()->toDateString())
            ->whereIn('vend_transaction_json->SErr', [0, 6]);
        $queryVendTransactions = $this->filterVendTransactionReport($queryVendTransactions, $request);
        $queryVendTransactions = $this->filterOperatorVendTransactionDB($queryVendTransactions);
        $queryVendTransactions = $queryVendTransactions
            ->select(
                'vends.id',
                'customers.id AS customer_id',
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'vends.name',
                'vends.code',
                DB::raw('PERIOD_DIFF(DATE_FORMAT(NOW(), "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) AS month_diff'),
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 1) AS gross_profit_margin'),
                DB::raw('SUM(CASE WHEN PERIOD_DIFF(DATE_FORMAT(NOW(), "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
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
        $currentDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone());

        $queryVendTransactions = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth()->toDateString())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth()->toDateString())
            ->whereIn('vend_transaction_json->SErr', [0, 6]);
        $queryVendTransactions = $this->filterVendTransactionReport($queryVendTransactions, $request);
        $queryVendTransactions = $this->filterOperatorVendTransactionDB($queryVendTransactions);
        $queryVendTransactions = $queryVendTransactions
            ->select(
                'products.id',
                'products.name',
                'products.code',
                DB::raw('PERIOD_DIFF(DATE_FORMAT(NOW(), "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) AS month_diff'),
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 1) AS gross_profit_margin'),
                DB::raw('SUM(CASE WHEN PERIOD_DIFF(DATE_FORMAT(NOW(), "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
            )
            ->groupBy('products.id', 'month_diff');

        $products = DB::query()
            ->fromSub($queryVendTransactions, 'transac')
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
        $currentDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone());
        $className = get_class(new Customer());

        $queryVendTransactions = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth()->toDateString())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth()->toDateString())
            ->whereIn('vend_transaction_json->SErr', [0, 6]);
        $queryVendTransactions = $this->filterVendTransactionReport($queryVendTransactions, $request);
        $queryVendTransactions = $this->filterOperatorVendTransactionDB($queryVendTransactions);
        $queryVendTransactions = $queryVendTransactions
            ->select(
                'categories.id',
                'categories.name',
                'categories.classname',
                DB::raw('PERIOD_DIFF(DATE_FORMAT(NOW(), "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) AS month_diff'),
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 1) AS gross_profit_margin'),
                DB::raw('SUM(CASE WHEN PERIOD_DIFF(DATE_FORMAT(NOW(), "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
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
        $currentDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone());

        $queryVendTransactions = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('location_types', 'customers.location_type_id', '=', 'location_types.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth()->toDateString())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth()->toDateString())
            ->whereIn('vend_transaction_json->SErr', [0, 6]);
        $queryVendTransactions = $this->filterVendTransactionReport($queryVendTransactions, $request);
        $queryVendTransactions = $this->filterOperatorVendTransactionDB($queryVendTransactions);
        $queryVendTransactions = $queryVendTransactions
            ->select(
                'location_types.id',
                'location_types.name',
                DB::raw('PERIOD_DIFF(DATE_FORMAT(NOW(), "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) AS month_diff'),
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 1) AS gross_profit_margin'),
                DB::raw('SUM(CASE WHEN PERIOD_DIFF(DATE_FORMAT(NOW(), "%Y%m"), DATE_FORMAT(vend_transactions.created_at, "%Y%m")) = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
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

    public function getStockCountQuery($request)
    {
        $currentDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone());

        $vendSnapshots = DB::table('vend_snapshots')
            ->leftJoin('vends', 'vends.id', '=', 'vend_snapshots.vend_id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_snapshots.customer_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_snapshots.operator_id')
            ->whereDate('vend_snapshots.created_at', '>=', $currentDate->copy()->startOfMonth()->toDateString())
            ->whereDate('vend_snapshots.created_at', '<=', $currentDate->copy()->endOfMonth()->toDateString());

        $vendSnapshots = $this->filterOperatorVendTransactionDB($vendSnapshots);
        $vendSnapshots = $vendSnapshots->select(
                'vend_snapshots.id AS id',
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                DB::raw('MONTH(vend_snapshots.created_at) AS month_number'),
                DB::raw('YEAR(vend_snapshots.created_at) AS year_number'),
                'vends.code AS vend_code',
                'vends.name AS vend_name',
                'vend_snapshots.created_at AS created_at',
                'vend_snapshots.parameter_json',
                'vend_snapshots.vend_channels_json',
            )
            ->groupBy('vends.id', 'year_number', 'month_number');

        return $vendSnapshots;
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

    private function yieldOneByOne($items) {
        foreach($items as $item) {
            yield $item;
        }
    }
}
