<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use App\Models\Setting;
use Illuminate\Http\Request;
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

        $setting = Setting::query()->first();
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
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
            'logoUrl' => $logoUrl,
            'smallLogoUrl' => $smallLogoUrl ?: $logoUrl,
            'defaultLogoUrl' => $defaultLogoUrl,
            'isCmsUrlSet' => !empty(env('CMS_URL')),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
            ],
        ]);
    }
}
