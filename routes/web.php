<?php

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\VendController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function() {
    Route::get('/vend', [VendController::class, 'index'])->name('vend');
    Route::get('/vend/{id}/temp/{duration?}', [VendController::class, 'temp'])->name('temp');
    Route::get('/vends/channel-error-logs-email', [VendController::class, 'channelErrorLogsEmail']);
    Route::get('/customer', [CustomerController::class, 'index'])->name('customer');
    Route::get('/transaction', [TransactionController::class, 'index'])->name('transaction');
});

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';
