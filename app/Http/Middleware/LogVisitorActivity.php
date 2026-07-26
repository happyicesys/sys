<?php

namespace App\Http\Middleware;

use App\Services\VisitorHistoryService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records one visitor_page_views row per Inertia page an authenticated user
 * opens (Admin > Visitor History).
 *
 * Two-phase on purpose:
 *
 *  1. BEFORE the response is built we decide, from the request alone, whether
 *     this looks like a page load and mint a visit uuid onto the request
 *     attributes. HandleInertiaRequests::share picks that uuid up and hands it
 *     to the browser as the `visitorVisit` prop, which is what lets the unload
 *     beacon report real dwell time against this exact row.
 *
 *  2. AFTER the response exists we only persist the row if it actually rendered
 *     a page — 200 + HTML/Inertia. Redirects, validation bounces, 403/404s and
 *     file downloads (Excel exports) are therefore never logged.
 *
 * Everything is wrapped in try/catch: this is an audit log, and it must never be
 * capable of breaking a page.
 */
class LogVisitorActivity
{
    /** Paths that are never page views regardless of what they return. */
    protected array $ignoredPaths = [
        'visitor-history/ping',
        'telescope',
        'telescope/*',
        'horizon',
        'horizon/*',
        'clockwork/*',
        '_debugbar/*',
        'broadcasting/*',
        'sanctum/*',
        'oauth/*',
        'livewire/*',
        'storage/*',
        'build/*',
        'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $uuid = null;

        try {
            if ($this->requestLooksLikePage($request)) {
                $uuid = (string) Str::uuid();
                $request->attributes->set('visitor_visit_uuid', $uuid);
            }
        } catch (\Throwable $e) {
            $uuid = null;
            Log::warning('VisitorHistory pre-dispatch failed: ' . $e->getMessage());
        }

        $response = $next($request);

        try {
            if ($uuid && $this->responseIsPage($response) && ($user = $request->user())) {
                app(VisitorHistoryService::class)->recordPageView($request, $uuid, (int) $user->id);
            }
        } catch (\Throwable $e) {
            Log::warning('VisitorHistory recordPageView failed: ' . $e->getMessage());
        }

        return $response;
    }

    protected function requestLooksLikePage(Request $request): bool
    {
        if (!config('visitor_history.enabled', true)) {
            return false;
        }

        if (!$request->user()) {
            return false;
        }

        if (!$request->isMethod('GET')) {
            return false;
        }

        if ($request->is(...$this->ignoredPaths)) {
            return false;
        }

        // Inertia partial reloads (router.reload({only: [...]})) refresh props on
        // the page the user is already sitting on — not a new visit.
        if ($request->header('X-Inertia-Partial-Data')) {
            return false;
        }

        // Speculative prefetches (<Link prefetch>, browser prerender) are pages
        // the user only hovered — logging them would put pages nobody opened
        // into an audit trail.
        if ($request->header('Purpose') === 'prefetch'
            || $request->header('X-Purpose') === 'preview'
            || $request->header('X-Moz') === 'prefetch'
            || str_contains((string) $request->header('Sec-Purpose'), 'prefetch')) {
            return false;
        }

        // Asset-looking paths (.js/.css/.png/...) never reach a controller that
        // renders a page.
        if (preg_match('/\.[a-z0-9]{2,5}$/i', $request->path())) {
            return false;
        }

        return $request->header('X-Inertia') || $request->acceptsHtml();
    }

    protected function responseIsPage(Response $response): bool
    {
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if ($response->headers->get('X-Inertia')) {
            return true;
        }

        // Streamed/binary downloads (Excel exports) fail this check, which is
        // exactly what we want — "page views only".
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
