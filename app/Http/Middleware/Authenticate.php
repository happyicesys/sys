<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Contracts\Auth\StatefulGuard;

class Authenticate extends Middleware
{
    /**
     * A deactivated user is thrown out on their next request, not only refused
     * at the login form: the web session is ended (remember-me cookie included)
     * and an API bearer token is revoked. Without this, Deactivate left anyone
     * already logged in working until their session expired.
     */
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, $guards);

        // parent::authenticate() called shouldUse() on the guard that matched.
        $guard = $this->auth->guard();
        $user = $guard->user();

        // Only an explicit "no" counts: a model made in memory (factory, freshly
        // created) has no is_active until the DB default is read back.
        $isActive = $user?->getAttribute('is_active');
        if (! $user || $isActive === null || $isActive) {
            return;
        }

        if ($guard instanceof StatefulGuard) {
            $guard->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->flash('status', __('Your account is inactive.'));
            }
        } elseif (method_exists($user, 'token') && $user->token()) {
            $user->token()->revoke();
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
