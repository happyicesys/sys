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
        ]);

        // Passport::loadKeysFrom(__DIR__.'/../secrets/oauth');
    }
}
