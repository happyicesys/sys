<?php

namespace App\Http\Controllers;

use App\Services\Grab\GrabStorePerformance;
use App\Support\Grab\GrabStoreVisibility;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Grab Performance (Brian, 2026-11-16): each Grab store's sales over a period against the period
 * before, ranked, to decide which machines stay on Grab and which move. Read only.
 */
class GrabPerformanceController extends Controller
{
    public function index(Request $request, GrabStorePerformance $performance)
    {
        $operatorIds = GrabStoreVisibility::narrow($request->operators);
        $days = (int) $request->days;
        $codes = trim((string) $request->codes);

        $report = $performance->report($operatorIds, $days, $codes);

        return Inertia::render('GrabPerformance/Index', [
            'report' => $report,
            'periods' => GrabStorePerformance::PERIODS,
            'filters' => [
                'operators' => $request->operators,
                'codes' => $codes,
                'days' => $report['days'],
            ],
        ]);
    }
}
