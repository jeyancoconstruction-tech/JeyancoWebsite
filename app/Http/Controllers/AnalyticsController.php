<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\Site;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;

/**
 * Analytics & Insights.
 *
 * The page is drawn with the figures for whatever filters its address
 * carries. Changing a filter asks data() for the same figures as JSON and
 * redraws in place, so nothing reloads and the choice survives in the URL.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics)
    {
        $filters = $analytics->filters($request->query());

        return view('analytics', [
            'filters'   => $filters,
            'ranges'    => AnalyticsService::RANGES,
            'statuses'  => AnalyticsService::STATUSES,
            'sites'     => Site::orderBy('name')->get(['id', 'name']),
            'shifts'    => Shift::orderBy('id')->get(['id', 'name']),
            'analytics' => $analytics->build($filters),
        ]);
    }

    /** The same figures as JSON, for a filter change or the minute's refresh. */
    public function data(Request $request, AnalyticsService $analytics)
    {
        return response()->json($analytics->build($analytics->filters($request->query())));
    }
}
