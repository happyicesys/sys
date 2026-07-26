<?php

namespace App\Http\Controllers;

use App\Http\Resources\VisitorPageViewResource;
use App\Http\Resources\VisitorSessionResource;
use App\Models\User;
use App\Models\VisitorPageView;
use App\Models\VisitorSession;
use App\Services\VisitorHistoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Admin > Visitor History.
 *
 * Read side only — rows are written by LogVisitorActivity and the auth
 * listeners. Two views share one page: 'sessions' (one row per login) and
 * 'pages' (the flat page-view log).
 */
class VisitorHistoryController extends Controller
{
    public function index(Request $request)
    {
        $view = in_array($request->input('view'), ['sessions', 'pages'], true)
            ? $request->input('view')
            : 'sessions';

        $numberPerPage = $request->input('numberPerPage') ?: 100;
        $perPage = $numberPerPage === 'All' ? 5000 : (int) $numberPerPage;

        // Default to the last 7 days so the page never opens on a full-table
        // scan. Parsed defensively: a hand-edited or stale bookmarked URL must
        // fall back to the default range, not 500 the page.
        $dateFrom = ($this->parseDate($request->input('dateFrom')) ?: Carbon::today()->subDays(6))->startOfDay();
        $dateTo = ($this->parseDate($request->input('dateTo')) ?: Carbon::today())->endOfDay();
        if ($dateTo->lt($dateFrom)) {
            $dateTo = $dateFrom->copy()->endOfDay();
        }

        $userIds = array_filter((array) $request->input('userIds', []));
        $ip = trim((string) $request->input('ip'));
        $path = trim((string) $request->input('path'));
        $deviceType = trim((string) $request->input('deviceType'));

        $sessions = null;
        $pageViews = null;

        if ($view === 'sessions') {
            $sessions = VisitorSessionResource::collection(
                $this->sessionQuery($userIds, $dateFrom, $dateTo, $ip, $path, $deviceType)
                    ->orderByDesc('login_at')
                    ->orderByDesc('id')
                    ->paginate($perPage)
                    ->withQueryString()
            );
        } else {
            $pageViews = VisitorPageViewResource::collection(
                $this->pageViewQuery($userIds, $dateFrom, $dateTo, $ip, $path)
                    ->orderByDesc('viewed_at')
                    ->orderByDesc('id')
                    ->paginate($perPage)
                    ->withQueryString()
            );
        }

        return Inertia::render('VisitorHistory/Index', [
            'view'      => $view,
            'sessions'  => $sessions,
            'pageViews' => $pageViews,
            'summary'   => $this->summary($userIds, $dateFrom, $dateTo, $ip, $path, $deviceType),
            'userOptions' => User::orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn ($u) => [
                    'id'   => $u->id,
                    'name' => $u->email ? $u->name . ' — ' . $u->email : $u->name,
                ]),
            'deviceTypeOptions' => [
                ['id' => 'desktop', 'name' => 'Desktop'],
                ['id' => 'mobile', 'name' => 'Mobile'],
                ['id' => 'tablet', 'name' => 'Tablet'],
                ['id' => 'bot', 'name' => 'Bot / script'],
            ],
            'appliedFilters' => [
                'dateFrom'      => $dateFrom->toDateString(),
                'dateTo'        => $dateTo->toDateString(),
                'userIds'       => array_values(array_map('intval', $userIds)),
                'ip'            => $ip,
                'path'          => $path,
                'deviceType'    => $deviceType,
                'numberPerPage' => $numberPerPage,
            ],
            'retentionDays'   => (int) config('visitor_history.retention_days', 90),
            'sessionLifetime' => (int) config('session.lifetime', 120),
        ]);
    }

    /** Page views for one login session — used by the row drill-down. */
    public function pageViews(Request $request, $visitorSessionId)
    {
        $session = VisitorSession::findOrFail($visitorSessionId);

        return response()->json([
            'data' => VisitorPageViewResource::collection(
                $session->pageViews()
                    ->with('user:id,name')
                    ->orderBy('viewed_at')
                    ->orderBy('id')
                    ->limit(500)
                    ->get()
            ),
        ]);
    }

    /**
     * Dwell-time beacon (navigator.sendBeacon from Authenticated.vue).
     *
     * Fire-and-forget by design: the browser cannot read the response, so this
     * always answers 204 and never validates loudly. `visit` is the uuid minted
     * by LogVisitorActivity for that exact page view, and we additionally match
     * on user_id so one signed-in user can never rewrite another's history.
     */
    public function ping(Request $request, VisitorHistoryService $service)
    {
        try {
            $user = $request->user();
            $uuid = (string) $request->input('visit');

            if ($user && $uuid !== '') {
                $view = VisitorPageView::where('visit_uuid', $uuid)
                    ->where('user_id', $user->id)
                    ->first();

                if ($view) {
                    $totalMs = max(0, (int) $request->input('total_ms', 0));
                    $activeMs = max(0, (int) $request->input('active_ms', 0));

                    // Guard against a tab left open for days skewing the column.
                    $total = min(intdiv($totalMs, 1000), 86400);
                    $active = min(intdiv($activeMs, 1000), 86400);

                    $view->update([
                        'left_at'          => $view->viewed_at
                            ? $view->viewed_at->copy()->addSeconds($total)
                            : now(),
                        'duration_seconds' => $total,
                        'active_seconds'   => min($active, $total),
                        'duration_source'  => 'beacon',
                    ]);
                }

                // 'unload' means the tab/window actually went away — the closest
                // thing we get to a logout for people who just close the browser.
                if ($request->input('reason') === 'unload' && $request->hasSession()) {
                    $sessionId = $request->session()->get(VisitorHistoryService::SESSION_KEY);
                    $service->endSession($sessionId ? (int) $sessionId : null, 'closed');
                }
            }
        } catch (\Throwable $e) {
            // Never surface anything to the browser for a beacon.
            Log::warning('VisitorHistory ping failed: ' . $e->getMessage());
        }

        return response()->noContent();
    }

    protected function sessionQuery(array $userIds, Carbon $dateFrom, Carbon $dateTo, string $ip, string $path, string $deviceType)
    {
        return VisitorSession::query()
            ->with(['user:id,name,email,operator_id', 'user.operator:id,code'])
            ->whereBetween('login_at', [$dateFrom, $dateTo])
            ->when($userIds, fn ($q) => $q->whereIn('user_id', $userIds))
            ->when($ip !== '', fn ($q) => $q->where('ip', 'LIKE', "%{$ip}%"))
            ->when($deviceType !== '', fn ($q) => $q->where('device_type', $deviceType))
            ->when($path !== '', fn ($q) => $q->whereExists(function ($sub) use ($path) {
                $sub->selectRaw(1)
                    ->from('visitor_page_views')
                    ->whereColumn('visitor_page_views.visitor_session_id', 'visitor_sessions.id')
                    ->where('visitor_page_views.path', 'LIKE', self::pathLike($path));
            }));
    }

    protected function pageViewQuery(array $userIds, Carbon $dateFrom, Carbon $dateTo, string $ip, string $path)
    {
        return VisitorPageView::query()
            ->with(['user:id,name,email'])
            ->whereBetween('viewed_at', [$dateFrom, $dateTo])
            ->when($userIds, fn ($q) => $q->whereIn('user_id', $userIds))
            ->when($ip !== '', fn ($q) => $q->where('ip', 'LIKE', "%{$ip}%"))
            ->when($path !== '', fn ($q) => $q->where('path', 'LIKE', self::pathLike($path)));
    }

    /**
     * Both session tiles in one round trip instead of two more full re-runs of
     * the (potentially LIKE-filtered) session query.
     */
    protected function summary(array $userIds, Carbon $dateFrom, Carbon $dateTo, string $ip, string $path, string $deviceType): array
    {
        $row = $this->sessionQuery($userIds, $dateFrom, $dateTo, $ip, $path, $deviceType)
            ->withoutEagerLoads()
            ->selectRaw('COUNT(*) as total_sessions, COUNT(DISTINCT user_id) as total_users')
            ->first();

        return [
            'sessions'   => (int) ($row->total_sessions ?? 0),
            'users'      => (int) ($row->total_users ?? 0),
            'page_views' => $this->pageViewQuery($userIds, $dateFrom, $dateTo, $ip, $path)->count(),
        ];
    }

    /** Null when the value is absent or not a date we can understand. */
    protected function parseDate($value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A page filter typed as a path ("/transactions") is anchored so MySQL can
     * use the visitor_page_views.path index; anything else is a contains match.
     */
    protected static function pathLike(string $path): string
    {
        return str_starts_with($path, '/') ? $path . '%' : '%' . $path . '%';
    }
}
