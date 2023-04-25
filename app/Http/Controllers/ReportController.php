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

        $queryThisMonth = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth())
            ->whereIn('vend_transaction_json->SErr', [0, 6])
            ->select(
                'vends.id',
                DB::raw('ROUND(SUM(JSON_EXTRACT(unit_cost_json, "$.revenue")), 2) AS revenue'),
                DB::raw('ROUND(SUM(JSON_EXTRACT(unit_cost_json, "$.gross_profit")), 2) AS gross_profit'),
                DB::raw('ROUND((SUM(JSON_EXTRACT(unit_cost_json, "$.gross_profit"))/ SUM(JSON_EXTRACT(unit_cost_json, "$.revenue"))) * 100, 0) AS gross_profit_margin')
            )
            ->groupBy('vends.id');


        $queryLastMonth = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonth()->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->subMonth()->endOfMonth())
            ->whereIn('vend_transaction_json->SErr', [0, 6])
            ->select(
                'vends.id',
                DB::raw('ROUND(SUM(JSON_EXTRACT(unit_cost_json, "$.revenue")), 2) AS revenue'),
                DB::raw('ROUND(SUM(JSON_EXTRACT(unit_cost_json, "$.gross_profit")), 2) AS gross_profit'),
                DB::raw('ROUND((SUM(JSON_EXTRACT(unit_cost_json, "$.gross_profit"))/ SUM(JSON_EXTRACT(unit_cost_json, "$.revenue"))) * 100, 0) AS gross_profit_margin')
            )
            ->groupBy('vends.id');

        $queryLastTwoMonth = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->whereDate('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth())
            ->whereDate('vend_transactions.created_at', '<=', $currentDate->copy()->subMonths(2)->endOfMonth())
            ->whereIn('vend_transaction_json->SErr', [0, 6])
            ->select(
                'vends.id',
                DB::raw('ROUND(SUM(JSON_EXTRACT(unit_cost_json, "$.revenue")), 2) AS revenue'),
                DB::raw('ROUND(SUM(JSON_EXTRACT(unit_cost_json, "$.gross_profit")), 2) AS gross_profit'),
                DB::raw('ROUND((SUM(JSON_EXTRACT(unit_cost_json, "$.gross_profit"))/ SUM(JSON_EXTRACT(unit_cost_json, "$.revenue"))) * 100, 0) AS gross_profit_margin')
            )
            ->groupBy('vends.id');


        $vends = Vend::query()
            ->with([
                'latestVendBinding.customer',
            ])
            ->joinSub($queryThisMonth, 'this_month', function($join) {
                $join->on('vends.id', '=', 'this_month.id');
            })
            ->joinSub($queryLastMonth, 'last_month', function($join) {
                $join->on('vends.id', '=', 'last_month.id');
            })
            ->joinSub($queryLastTwoMonth, 'last_two_month', function($join) {
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
            'gross_profit_margin' => $grossProfitTotal/ $revenueTotal * 100,
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
        $currentDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth['id'])->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone());
        $className = get_class(new Customer());

        $products = Product::query()
        ->withSum([
            'vendTransactions AS this_month_revenue' => function($query) use ($currentDate) {
                $query->where('vend_transactions.created_at', '>=', $currentDate->copy()->startOfMonth())
                    ->where('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth());
            },
        ], 'unit_cost_json->revenue')
        ->withSum([
            'vendTransactions AS this_month_gross_profit' => function($query) use ($currentDate) {
                $query->where('vend_transactions.created_at', '>=', $currentDate->copy()->startOfMonth())
                    ->where('vend_transactions.created_at', '<=', $currentDate->copy()->endOfMonth());
            },
        ], 'unit_cost_json->gross_profit')
        ->withSum([
            'vendTransactions AS last_month_revenue' => function($query) use ($currentDate) {
                $query->where('vend_transactions.created_at', '>=', $currentDate->copy()->subMonth()->startOfMonth())
                    ->where('vend_transactions.created_at', '<=', $currentDate->copy()->subMonth()->endOfMonth());
            },
        ], 'unit_cost_json->revenue')
        ->withSum([
            'vendTransactions AS last_month_gross_profit' => function($query) use ($currentDate) {
                $query->where('vend_transactions.created_at', '>=', $currentDate->copy()->subMonth()->startOfMonth())
                    ->where('vend_transactions.created_at', '<=', $currentDate->copy()->subMonth()->endOfMonth());
            },
        ], 'unit_cost_json->gross_profit')
        ->withSum([
            'vendTransactions AS last_two_month_revenue' => function($query) use ($currentDate) {
                $query->where('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth())
                    ->where('vend_transactions.created_at', '<=', $currentDate->copy()->subMonths(2)->endOfMonth());
            },
        ], 'unit_cost_json->revenue')
        ->withSum([
            'vendTransactions AS last_two_month_gross_profit' => function($query) use ($currentDate) {
                $query->where('vend_transactions.created_at', '>=', $currentDate->copy()->subMonths(2)->startOfMonth())
                    ->where('vend_transactions.created_at', '<=', $currentDate->copy()->subMonths(2)->endOfMonth());
            },
        ], 'unit_cost_json->gross_profit')
        ->filterIndex($request)
        ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
        ->withQueryString();
        dd($products->toArray());

        return Inertia::render('Report/IndexProduct', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'products' => ProductResource::collection($products),
        ]);
    }

    public function indexCategory(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $page = $request->page ? $request->page : 1;
        $startDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())->subMonths(2)->startOfMonth() : Carbon::today()->setTimezone($this->getUserTimezone())->subMonths(2)->startOfMonth();
        $endDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())->endOfMonth() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfMonth();
        $className = get_class(new Customer());
    }
}
