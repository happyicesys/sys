<?php

namespace App\Providers;

use App\Contracts\Citybox\ChillerGateway;
use App\Services\Citybox\CityboxOpenapiGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the smart-chiller supplier abstraction to its CityBox implementation.
 * Everything in the ops/inventory layer type-hints ChillerGateway; swapping the
 * supplier (or injecting a FakeChillerGateway in tests) happens ONLY here.
 */
class CityboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChillerGateway::class, CityboxOpenapiGateway::class);
    }
}
