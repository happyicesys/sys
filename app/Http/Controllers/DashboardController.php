<?php

namespace App\Http\Controllers;

use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $dateFrom = $request->currentFilterDate ? explode(',',$request->currentFilterDate)[0] : Carbon::today()->setTimezone($this->getUserTimezone())->toDateString();
        $dateTo = $request->currentFilterDate ? explode(',',$request->currentFilterDate)[1] : Carbon::today()->setTimezone($this->getUserTimezone())->toDateString();

        return Inertia::render('Dashboard', [
            'vendTransactions' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
        ]);
    }
}
