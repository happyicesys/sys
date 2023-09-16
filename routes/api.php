<?php

use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\VendController;
use App\Http\Controllers\Api\Client\ClientController;
use App\Http\Controllers\Api\V1\VendDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('client')
    ->middleware(['throttle:client'])
    ->middleware('auth:api')
    ->group(function() {
        Route::post('/transactions', [ClientController::class, 'getTransactions']);
        Route::post('/channels', [ClientController::class, 'getChannels']);
    });

Route::prefix('v1')->group(function() {
    Route::post('/vend-data', [VendDataController::class, 'create']);
    Route::post('/customer/migrate', [CustomerController::class, 'migrate']);
    Route::post('/payment-gateway-status/{company?}', [PaymentController::class, 'createPaymentGatewayLog']);
    Route::get('/binded-vends', [VendDataController::class, 'getBindedVends']);
    Route::get('/payment-merchants/{countryCode}/{paymentGatewayName}', [PaymentController::class, 'getPaymentMerchantsApi']);
});

Route::prefix('delivery')->group(function() {
    Route::prefix('grab')->group(function() {
        Route::get('/merchant/menu', [DeliveryController::class, 'getMenu']);
        Route::get('/categories/{operatorId}/{type}', [DeliveryController::class, 'getCategories']);
        Route::get('/oauth/{operatorId}/{type}', [DeliveryController::class, 'getOauth']);

    });
});

// Internal api
Route::prefix('vends')->group(function() {
    Route::get('/search/{code?}', [VendController::class, 'searchVendCode']);
});



