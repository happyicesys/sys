<?php

namespace App\Services;

use App\Models\VisitorPageView;
use App\Models\VisitorSession;
use App\Support\UserAgentParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Write side of Admin > Visitor History. Every public method here is called from
 * the request hot path, so each one is wrapped by its caller in a try/catch —
 * an audit log must never be able to break a page load.
 *
 * Session bookkeeping lives in two session keys:
 *   visitor_history.session_id    the visitor_sessions row for this login
 *   visitor_history.last_view_id  the still-open visitor_page_views row, so we
 *                                 can close it with a single primary-key update
 *                                 when the next page is opened
 */
class VisitorHistoryService
{
    public const SESSION_KEY   = 'visitor_history.session_id';
    public const USER_KEY      = 'visitor_history.user_id';
    public const LAST_VIEW_KEY = 'visitor_history.last_view_id';

    /**
     * Real client IP.
     *
     * TrustProxies::$proxies is null in this app, so Request::ip() returns the
     * load balancer / nginx address whenever the app sits behind one. We read
     * the forwarded headers here — and ONLY here — rather than flipping the
     * global TrustProxies setting, which would change the resolved IP for every
     * request in the app (rate limiting, sessions, gateway webhooks).
     */
    public function clientIp(Request $request): ?string
    {
        // An explicit header wins when the deployment sets one it controls
        // (VISITOR_HISTORY_IP_HEADER, e.g. CF-Connecting-IP behind Cloudflare).
        $configured = config('visitor_history.ip_header');
        if ($configured && ($value = $request->header($configured))) {
            $value = trim(explode(',', $value)[0]);
            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return substr($value, 0, 45);
            }
        }

        // Otherwise take the LAST X-Forwarded-For entry, not the first. A client
        // can send its own X-Forwarded-For; nginx's $proxy_add_x_forwarded_for
        // APPENDS the address it actually saw, so the last hop is the only entry
        // the client cannot forge. Taking the first would let anyone write any IP
        // they liked into an audit log.
        if ($forwarded = $request->header('X-Forwarded-For')) {
            $parts = array_map('trim', explode(',', $forwarded));
            for ($i = count($parts) - 1; $i >= 0; $i--) {
                if (filter_var($parts[$i], FILTER_VALIDATE_IP)) {
                    return substr($parts[$i], 0, 45);
                }
            }
        }

        $ip = $request->ip();

        return $ip ? substr($ip, 0, 45) : null;
    }

    /**
     * Open a visitor session row and remember it on the PHP session. Called from
     * the Login listener; also called lazily by the middleware so users who were
     * already signed in when this shipped still get a session row.
     */
    public function startSession(Request $request, int $userId): ?int
    {
        $ua = (string) $request->userAgent();
        $parsed = UserAgentParser::parse($ua);

        $session = VisitorSession::create([
            'user_id'          => $userId,
            'ip'               => $this->clientIp($request),
            'user_agent'       => mb_substr($ua, 0, 512),
            'device_type'      => $parsed['device_type'],
            'platform'         => $parsed['platform'],
            'browser'          => $parsed['browser'],
            'browser_version'  => $parsed['browser_version'],
            'login_at'         => now(),
            'last_activity_at' => now(),
            'page_view_count'  => 0,
        ]);

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $session->id);
            $request->session()->put(self::USER_KEY, $userId);
            $request->session()->forget(self::LAST_VIEW_KEY);
        }

        return $session->id;
    }

    /**
     * The visitor session id for this request, creating one if the PHP session
     * has none yet or points at a row that belongs to a different user (which
     * can only happen if someone logs in as another user without the Login
     * event firing).
     */
    public function resolveSessionId(Request $request, int $userId): ?int
    {
        if (!$request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);
        $owner = $request->session()->get(self::USER_KEY);

        // Compared straight off the PHP session so the hot path costs zero extra
        // SELECTs per page view.
        if ($id && (int) $owner === $userId) {
            return (int) $id;
        }

        return $this->startSession($request, $userId);
    }

    /**
     * Persist one page view and close the previous one with an inferred
     * duration. Returns nothing — the caller already holds the uuid.
     */
    public function recordPageView(Request $request, string $uuid, int $userId): void
    {
        $sessionId = $this->resolveSessionId($request, $userId);

        $this->closeOpenPageView($request, 'inferred');

        $view = VisitorPageView::create([
            'visit_uuid'         => $uuid,
            'visitor_session_id' => $sessionId,
            'user_id'            => $userId,
            'path'               => mb_substr('/' . ltrim($request->path(), '/'), 0, 191),
            'query_string'       => $request->getQueryString()
                ? mb_substr($request->getQueryString(), 0, 500)
                : null,
            'route_name'         => $request->route()?->getName(),
            'ip'                 => $this->clientIp($request),
            'viewed_at'          => now(),
        ]);

        if ($request->hasSession()) {
            $request->session()->put(self::LAST_VIEW_KEY, $view->id);
        }

        if ($sessionId) {
            // A new page view means the user is back — clear any "closed" flag a
            // previous unload beacon (or an F5 reload) left behind. A 'logout'
            // end is never reached here because logging out invalidates the PHP
            // session, so the next login starts a fresh visitor session row.
            VisitorSession::whereKey($sessionId)->update([
                'last_activity_at' => now(),
                'ended_at'         => null,
                'end_reason'       => null,
                'page_view_count'  => DB::raw('page_view_count + 1'),
                'updated_at'       => now(),
            ]);
        }
    }

    /**
     * Stamp left_at/duration on the page view the user is navigating away from.
     * Never overwrites a duration the browser beacon already reported.
     */
    public function closeOpenPageView(Request $request, string $source): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $lastId = $request->session()->get(self::LAST_VIEW_KEY);
        if (!$lastId) {
            return;
        }

        // Single primary-key UPDATE with the duration computed in SQL, so the
        // page-render hot path costs one round trip instead of SELECT+UPDATE.
        // `left_at IS NULL` makes it a no-op when the browser beacon already
        // reported a real (more accurate) duration for this row.
        VisitorPageView::whereKey($lastId)
            ->whereNull('left_at')
            ->update([
                'left_at'          => now(),
                'duration_seconds' => DB::raw('GREATEST(0, TIMESTAMPDIFF(SECOND, viewed_at, NOW()))'),
                'duration_source'  => $source,
                'updated_at'       => now(),
            ]);
    }

    /** Close the visitor session. $reason is 'logout' or 'closed'. */
    public function endSession(?int $sessionId, string $reason): void
    {
        if (!$sessionId) {
            return;
        }

        try {
            VisitorSession::whereKey($sessionId)->whereNull('ended_at')->update([
                'ended_at'   => now(),
                'end_reason' => $reason,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('VisitorHistory endSession failed: ' . $e->getMessage());
        }
    }
}
