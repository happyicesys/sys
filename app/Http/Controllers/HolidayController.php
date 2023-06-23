<?php

namespace App\Http\Controllers;

use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use App\Traits\GetUserTimezone;
use App\Traits\HasFilter;
use App\Traits\HasMonthOption;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HolidayController extends Controller
{
    use HasFilter, HasMonthOption, GetUserTimezone;

    public function index(Request $request)
    {
        $request->merge(['currentYear' => isset($request->currentYear) ? Carbon::createFromFormat('Y', $request->currentYear)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone())]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'date_from';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Holiday/Index', [
            'holidays' => HolidayResource::collection(
                Holiday::query()
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->currentYear, function($query, $search) {
                        $query
                        ->whereDate('date_from', '>=', $search->copy()->startOfYear()->toDateString())
                        ->whereDate('date_to', '<=', $search->copy()->endOfYear()->toDateString());
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'yearOptions' => $this->getYearOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        Holiday::create($request->all());

        return redirect()->route('holidays');
    }

    public function update(Request $request, $holidayId)
    {
        $request->validate([
            'name' => 'required',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        $holiday = Holiday::findOrFail($holidayId);
        $holiday->update($request->all());

        return redirect()->route('holidays');
    }

    public function delete($holidayId)
    {
        $holiday = Holiday::findOrFail($holidayId);
        $holiday->delete();

        return redirect()->route('holidays');
    }
}
