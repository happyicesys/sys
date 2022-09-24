<?php

use App\Http\Controllers\ProfileController;
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
    Route::get('/vends', [VendController::class, 'index'])->name('vends');
    Route::get('/vend/{id}/temp', [VendController::class, 'temp'])->name('temp');
    Route::get('/vends/transactions', [VendController::class, 'transactionIndex'])->name('vends-transactions');
    Route::get('/vends/channel-error-logs-email', [VendController::class, 'channelErrorLogsEmail']);
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers');
    Route::get('/profiles', [ProfileController::class, 'index'])->name('profiles');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
});

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';
