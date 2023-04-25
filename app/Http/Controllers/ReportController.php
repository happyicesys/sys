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
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    use HasMonthOption, GetUserTimezone;

    public function indexVm(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $page = $request->page ? $request->page : 1;
        $currentDate = $request->currentMonth ? Carbon::createFromFormat('Y-m', $request->currentMonth['id'])->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone());
        $className = get_class(new Customer());

        $vends = Vend::query()
        ->with([
            'latestVendBinding.customer',
        ])
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
        // dd($vends->toArray());

        return Inertia::render('Report/IndexVm', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
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
