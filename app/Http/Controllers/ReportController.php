<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\VendResource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Traits\GetUserTimezone;
use App\Traits\HasMonthOption;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    use HasMonthOption, GetUserTimezone;

    public function indexVm(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $page = $request->page ? $request->page : 1;
        $currentDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone());
        $className = get_class(new Customer());

        $queryThisMonth = VendTransaction::query()
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth())
            ->filterReport($request)
            ->select(
                'vends.id',
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 0) AS gross_profit_margin')
            )
            ->groupBy('vends.id');


        $queryLastMonth = VendTransaction::query()
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonth()->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->subMonth()->endOfMonth())
            ->filterReport($request)
            ->select(
                'vends.id',
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 0) AS gross_profit_margin')
            )
            ->groupBy('vends.id');

        $queryLastTwoMonth = VendTransaction::query()
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->subMonths(2)->endOfMonth())
            ->filterReport($request)
            ->select(
                'vends.id',
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 0) AS gross_profit_margin')
            )
            ->groupBy('vends.id');


        $vends = Vend::query()
            ->with([
                'latestVendBinding.customer',
            ])
            ->leftJoinSub($queryThisMonth, 'this_month', function($join) {
                $join->on('vends.id', '=', 'this_month.id');
            })
            ->leftJoinSub($queryLastMonth, 'last_month', function($join) {
                $join->on('vends.id', '=', 'last_month.id');
            })
            ->leftJoinSub($queryLastTwoMonth, 'last_two_month', function($join) {
                $join->on('vends.id', '=', 'last_two_month.id');
            })
            ->select(
                'vends.id',
                'vends.name',
                'vends.code',
                'this_month.revenue AS this_month_revenue',
                'this_month.gross_profit AS this_month_gross_profit',
                'this_month.gross_profit_margin AS this_month_gross_profit_margin',
                'last_month.revenue AS last_month_revenue',
                'last_month.gross_profit AS last_month_gross_profit',
                'last_month.gross_profit_margin AS last_month_gross_profit_margin',
                'last_two_month.revenue AS last_two_month_revenue',
                'last_two_month.gross_profit AS last_two_month_gross_profit',
                'last_two_month.gross_profit_margin AS last_two_month_gross_profit_margin',
            )
            ->filterIndex($request)
            ->orderBy('vends.code', 'ASC')
            ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

            $revenueTotal = collect((clone $vends)->items())->sum(function($vend) {
                return $vend->this_month_revenue/ 100;
            });

            $grossProfitTotal = collect((clone $vends)->items())->sum(function($vend) {
                return $vend->this_month_gross_profit/ 100;
            });

        $totals = [
            'revenue' => $revenueTotal,
            'gross_profit' => $grossProfitTotal,
            'gross_profit_margin' => $revenueTotal ? ($grossProfitTotal/ $revenueTotal * 100) : 0,
        ];

        return Inertia::render('Report/IndexVm', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'totals' => $totals,
            'vends' => VendResource::collection($vends),
        ]);
    }

    public function indexProduct(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $page = $request->page ? $request->page : 1;
        $currentDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone());
        $className = get_class(new Customer());

        $queryThisMonth = VendTransaction::query()
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth())
            ->filterReport($request)
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 0) AS gross_profit_margin')
            )
            ->groupBy('products.id');

        $queryLastMonth = VendTransaction::query()
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonth()->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->subMonth()->endOfMonth())
            ->filterReport($request)
            ->select(
                'products.id',
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 0) AS gross_profit_margin')
            )
            ->groupBy('products.id');

            // dd($queryLastMonth->get()->toArray());

        $queryLastTwoMonth = VendTransaction::query()
            ->leftJoin('products', 'vend_transactions.product_id', '=', 'products.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->subMonths(2)->endOfMonth())
            ->filterReport($request)
            ->select(
                'products.id',
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 0) AS gross_profit_margin')
            )
            ->groupBy('products.id');


        $products = Product::query()
            ->leftJoinSub($queryThisMonth, 'this_month', function($join) {
                $join->on('products.id', '=', 'this_month.id');
            })
            ->leftJoinSub($queryLastMonth, 'last_month', function($join) {
                $join->on('products.id', '=', 'last_month.id');
            })
            ->leftJoinSub($queryLastTwoMonth, 'last_two_month', function($join) {
                $join->on('products.id', '=', 'last_two_month.id');
            })
            ->where('products.is_inventory', true)
            ->select(
                'products.id',
                'products.name',
                'products.code',
                'this_month.revenue AS this_month_revenue',
                'this_month.gross_profit AS this_month_gross_profit',
                'this_month.gross_profit_margin AS this_month_gross_profit_margin',
                'last_month.revenue AS last_month_revenue',
                'last_month.gross_profit AS last_month_gross_profit',
                'last_month.gross_profit_margin AS last_month_gross_profit_margin',
                'last_two_month.revenue AS last_two_month_revenue',
                'last_two_month.gross_profit AS last_two_month_gross_profit',
                'last_two_month.gross_profit_margin AS last_two_month_gross_profit_margin',
            )
            ->filterIndex($request)
            ->orderBy('products.code', 'ASC')
            ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

            $revenueTotal = collect((clone $products)->items())->sum(function($product) {
                return $product->this_month_revenue/ 100;
            });

            $grossProfitTotal = collect((clone $products)->items())->sum(function($product) {
                return $product->this_month_gross_profit/ 100;
            });

        $totals = [
            'revenue' => $revenueTotal,
            'gross_profit' => $grossProfitTotal,
            'gross_profit_margin' => $revenueTotal ? ($grossProfitTotal/ $revenueTotal * 100) : 0,
        ];

        return Inertia::render('Report/IndexProduct', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'totals' => $totals,
            'products' => ProductResource::collection($products),
        ]);
    }

    public function indexCategory(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $page = $request->page ? $request->page : 1;
        $currentDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone());
        $className = get_class(new Customer());

        $queryThisMonth = VendTransaction::query()
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('vend_bindings', function($join) {
                $join->on('vend_bindings.vend_id', '=', 'vends.id')
                    ->where('vend_bindings.is_active', true)
                    ->orderBy('begin_date', 'DESC')
                    ->limit(1);
            })
            ->leftJoin('customers', 'vend_bindings.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'customers.category_id', '=', 'categories.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth())
            ->filterReport($request)
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 0) AS gross_profit_margin')
            )
            ->groupBy('categories.id');

        $queryLastMonth = VendTransaction::query()
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('vend_bindings', function($join) {
                $join->on('vend_bindings.vend_id', '=', 'vends.id')
                    ->where('vend_bindings.is_active', true)
                    ->orderBy('begin_date', 'DESC')
                    ->limit(1);
            })
            ->leftJoin('customers', 'vend_bindings.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'customers.category_id', '=', 'categories.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonth()->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->subMonth()->endOfMonth())
            ->filterReport($request)
            ->select(
                'categories.id',
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 0) AS gross_profit_margin')
            )
            ->groupBy('categories.id');

            // dd($queryLastMonth->get()->toArray());

        $queryLastTwoMonth = VendTransaction::query()
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('vend_bindings', function($join) {
                $join->on('vend_bindings.vend_id', '=', 'vends.id')
                    ->where('vend_bindings.is_active', true)
                    ->orderBy('begin_date', 'DESC')
                    ->limit(1);
            })
            ->leftJoin('customers', 'vend_bindings.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'customers.category_id', '=', 'categories.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->subMonths(2)->endOfMonth())
            ->filterReport($request)
            ->select(
                'categories.id',
                DB::raw('SUM(revenue) AS revenue'),
                DB::raw('SUM(gross_profit) AS gross_profit'),
                DB::raw('ROUND(SUM(gross_profit) * 100/ SUM(revenue), 0) AS gross_profit_margin')
            )
            ->groupBy('categories.id');


        $categories = Category::query()
            ->leftJoinSub($queryThisMonth, 'this_month', function($join) {
                $join->on('categories.id', '=', 'this_month.id');
            })
            ->leftJoinSub($queryLastMonth, 'last_month', function($join) {
                $join->on('categories.id', '=', 'last_month.id');
            })
            ->leftJoinSub($queryLastTwoMonth, 'last_two_month', function($join) {
                $join->on('categories.id', '=', 'last_two_month.id');
            })
            ->where('classname', $className)
            ->select(
                'categories.id',
                'categories.name',
                'this_month.revenue AS this_month_revenue',
                'this_month.gross_profit AS this_month_gross_profit',
                'this_month.gross_profit_margin AS this_month_gross_profit_margin',
                'last_month.revenue AS last_month_revenue',
                'last_month.gross_profit AS last_month_gross_profit',
                'last_month.gross_profit_margin AS last_month_gross_profit_margin',
                'last_two_month.revenue AS last_two_month_revenue',
                'last_two_month.gross_profit AS last_two_month_gross_profit',
                'last_two_month.gross_profit_margin AS last_two_month_gross_profit_margin',
            )
            ->filterIndex($request)
            ->orderBy('categories.name', 'ASC')
            ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

            $revenueTotal = collect((clone $categories)->items())->sum(function($category) {
                return $category->this_month_revenue/ 100;
            });

            $grossProfitTotal = collect((clone $categories)->items())->sum(function($category) {
                return $category->this_month_gross_profit/ 100;
            });

        $totals = [
            'revenue' => $revenueTotal,
            'gross_profit' => $grossProfitTotal,
            'gross_profit_margin' => $revenueTotal ? ($grossProfitTotal/ $revenueTotal * 100) : 0,
        ];

        return Inertia::render('Report/IndexCategory', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'totals' => $totals,
            'categories' => CategoryResource::collection($categories),
        ]);
    }
}
