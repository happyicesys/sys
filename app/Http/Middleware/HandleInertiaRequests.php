<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
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
        // dd($request->user() && $request->user()->roles && $request->user()->roles->first() ? $request->user()->roles->first()->permissions->pluck('name')->all() : null, $request->user() ? $request->user()->roles->pluck('name')->all() : null);
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
                'operator' => $request->user() && $request->user()->operator ? $request->user()->operator : null,
                'operator.name' => $request->user() && $request->user()->operator ? $request->user()->operator->name : null,
                'roles' => function () use ($request) {
                    return ( $request->user() ? $request->user()->roles->pluck('name')->all() : null );
                },
                'permissions' => function () use ($request) {
                    return ( $request->user() && $request->user()->roles && $request->user()->roles->first() ? $request->user()->roles->first()->permissions->pluck('name')->all() : null );
                },
                'operatorRole' => $request->user() ? $request->user()->hasRole('operator') : null,
                'profile' => $request->user() ? $request->user()->profile : null,
                'profile.baseCurrency' => $request->user() ? $request->user()->profile->baseCurrency : null,
                'timezone' => $request->user() and $request->user()->operator ? $request->user()->operator->timezone : 'Asia/Singapore',
            ],
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
        ]);
    }
}
