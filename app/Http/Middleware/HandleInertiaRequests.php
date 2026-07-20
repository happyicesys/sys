<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request)
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request)
    {
        $user = $request->user();
        $operator = $user?->operator;
        if ($operator) {
            $operator->loadMissing('logo');
        }

        // Cached singleton — flushed automatically whenever the Setting row
        // is saved, so the value is always identical to a fresh query.
        $setting = Setting::singleton();
        $allowOverrideIds = collect($setting?->allow_overwrite_logo_operator_ids_array ?? [])
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $defaultLogoUrl = env('APP_LOGO_URL');
        $logoUrl = $defaultLogoUrl;
        $canOverrideLogo = false;

        if ($operator) {
            $canOverrideLogo = $allowOverrideIds->contains((int) $operator->id);
            if ($canOverrideLogo && $operator->logo?->full_url) {
                $logoUrl = $operator->logo->full_url;
            }
        }

        $smallLogoUrl = env('APP_SMALL_LOGO_URL');

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user,
                'operator' => $operator,
                'operator.name' => $operator ? $operator->name : null,
                'operatorCountry' => $operator ? $operator->country : null,
                'roles' => function () use ($request) {
                    return ($request->user() ? $request->user()->roles->pluck('name')->all() : null);
                },
                'permissions' => function () use ($request) {
                    return ($request->user() && $request->user()->roles && $request->user()->roles->first() ? $request->user()->roles->first()->permissions->pluck('name')->all() : null);
                },
                'operatorRole' => $user ? $user->hasRole('operator') : null,
                'profile' => $user ? $user->profile : null,
                // 'profile.baseCurrency' => $request->user() ? $request->user()->profile->baseCurrency : null,
                'timezone' => $operator ? $operator->timezone : config('app.timezone'),
                'canOverrideOperatorLogo' => $canOverrideLogo,
                'operatorLogoUrl' => $operator?->logo?->full_url,
            ],
            'ziggy' => function () use ($request) {
                return array_merge($this->cachedZiggyArray(), [
                    'location' => $request->url(),
                ]);
            },
            'logoUrl' => $logoUrl,
            'smallLogoUrl' => $smallLogoUrl ?: $logoUrl,
            'defaultLogoUrl' => $defaultLogoUrl,
            'isCmsUrlSet' => !empty(env('CMS_URL')),
            // Address-autofill map provider. When unset (or set to an
            // unsupported value) the address search API is disabled and the
            // Building/Street fields fall back to manual entry. Only 'onemap'
            // is wired into the frontend SearchAddressInput today.
            'mapProvider' => env('MAP_PROVIDER'),
            // App-wide reporting floor (data genesis). Frontend derived figures
            // — e.g. CustomerIndex "Avg Mthly Sales $" month count — must use
            // the same floor as the backend. See config/reporting.php.
            'reportingFloorDate' => config('reporting.floor_date', '2023-01-01'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
                'mcpNewToken' => fn () => $request->session()->get('mcpNewToken'),
            ],
            // Messenger-style unread-note badges for the sidebar, keyed by menu
            // href. Resolved as a closure so it evaluates AFTER the controller
            // body — the page being opened has already stamped last_viewed_at,
            // so its own badge reads 0 ("clear on visit") while other pages
            // keep their accurate counts. See NoteNotificationService.
            'noteBadges' => function () use ($user) {
                if (!$user) {
                    return new \stdClass();
                }
                return app(\App\Services\NoteNotificationService::class)
                    ->badgeCounts($user);
            },
            // Separate @-mention badge counts keyed by href — clears on visit
            // (uses last_viewed_at) just like noteBadges. See NoteNotificationService.
            'noteMentionBadges' => function () use ($user) {
                if (!$user) {
                    return new \stdClass();
                }
                return app(\App\Services\NoteNotificationService::class)
                    ->mentionBadgeCounts($user);
            },
        ]);
    }

    /**
     * The Ziggy route map (~540 routes) is identical for every request until
     * the route files change, yet it was being rebuilt on each request. Cache
     * it keyed by the route files' mtimes + the request root URL (Ziggy embeds
     * url('/') in its payload, which is host-dependent), so any deploy that
     * touches a route file generates a new key and the stale entry simply
     * expires. Output is byte-identical to (new Ziggy)->toArray().
     */
    protected function cachedZiggyArray(): array
    {
        $stamp = rtrim(url('/'), '/');
        foreach (glob(base_path('routes/*.php')) ?: [] as $file) {
            $stamp .= '|' . $file . ':' . @filemtime($file);
        }

        return Cache::remember('ziggy_routes_' . md5($stamp), 86400, function () {
            return (new Ziggy)->toArray();
        });
    }
}
