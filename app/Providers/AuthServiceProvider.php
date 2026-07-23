<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });

        Passport::tokensCan([
            'mart.partner_api' => 'Grab Mart Delivery API',
            'food.partner_api' => 'Grab Food Delivery API',
            'mcp' => 'Query the mark1 database (read-only) through Claude',
        ]);

        // Passport::loadKeysFrom(__DIR__.'/../secrets/oauth');

        Passport::personalAccessTokensExpireIn(now()->addYears(5));

        // MCP connector (authorization-code) token lifetimes. Personal access
        // tokens (machines / APK) keep the 5-year expiry above — unaffected.
        Passport::tokensExpireIn(now()->addDays(30));
        Passport::refreshTokensExpireIn(now()->addDays(180));
    }
}
