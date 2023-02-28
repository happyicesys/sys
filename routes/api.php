<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Api\Client\ClientController;
use App\Http\Controllers\Api\V1\VendDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('client')
    ->middleware(['throttle:client'])
    ->middleware('auth:sanctum')
    ->group(function() {
        Route::post('/transactions', [ClientController::class, 'getTransactions']);
        Route::post('/channels', [ClientController::class, 'getChannels']);
    });

Route::prefix('v1')->middleware(['throttle:api'])->group(function() {
    Route::post('/vend-data', [VendDataController::class, 'create']);
    Route::post('/customer/migrate', [CustomerController::class, 'migrate']);
    Route::post('/payment-gateway-status', [PaymentController::class, 'createPaymentResult']);
});



