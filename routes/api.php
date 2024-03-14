<?php

use App\Http\Controllers\DeliveryPlatformController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\VendController;
use App\Http\Controllers\VoucherController;
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
    Route::post('/customers/person/{personID?}', [CustomerController::class, 'getCustomersByPersonID']);
    Route::post('/payment-gateway-status/{company?}', [PaymentController::class, 'createPaymentGatewayLog']);
    Route::get('/binded-vends', [VendDataController::class, 'getBindedVends']);
    Route::get('/payment-merchants/{countryCode}/{paymentGatewayName}', [PaymentController::class, 'getPaymentMerchantsApi']);
    Route::post('/vends/{id}/upload-log', [VendDataController::class, 'uploadLog']);
    Route::post('/content/vends/{code}', [VendDataController::class, 'getVendMediaContent']);
});

Route::prefix('delivery')->group(function() {
    Route::prefix('grab')->group(function() {
        Route::get('/categories/{operatorId}/{type}', [DeliveryPlatformController::class, 'getCategories']);
        Route::get('/merchant/menu', [DeliveryPlatformController::class, 'getGrabMenu']);
        Route::get('/oauth/{deliveryPlatformOperatorId}', [DeliveryPlatformController::class, 'getOauth']);
        Route::post('/order/create', [DeliveryPlatformController::class, 'createGrabOrder']);
        Route::put('/order/update', [DeliveryPlatformController::class, 'updateGrabOrder']);
        Route::post('/sync-menu-webhook', [DeliveryPlatformController::class, 'syncGrabMenuWebhook']);
    });
    Route::post('/order/search/{dispenseSearch?}', [DeliveryPlatformController::class, 'searchGrabOrder']);
    Route::post('/order/complaint', [DeliveryPlatformController::class, 'submitGrabOrderComplaint']);
});

Route::prefix('vouchers')->group(function() {
    Route::post('/search', [VoucherController::class, 'searchVoucherCode']);
});

// Internal api
Route::prefix('customers')->group(function() {
    Route::get('/search/{search?}', [CustomerController::class, 'search']);
});

Route::prefix('vends')->group(function() {
    Route::get('/search/{code?}', [VendController::class, 'searchVendCode']);
    Route::get('/search/operator/{code?}', [VendController::class, 'searchVendCodeWithOperator']);
});





