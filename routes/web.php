<?php

use App\Http\Controllers\BankController;
use App\Http\Controllers\CashlessProviderController;
use App\Http\Controllers\CashlessTerminalController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryGroupController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PaymentTermController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\SimcardController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TelcoController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendController;
use App\Http\Controllers\ZoneController;
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

// Route::get('/', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/', function () {

    return redirect('/vends');
    // return Inertia::render('Dashboard', [
    //     'canLogin' => Route::has('login'),
    //     'canRegister' => Route::has('register'),
    //     'laravelVersion' => Application::VERSION,
    //     'phpVersion' => PHP_VERSION,
    // ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function() {

    Route::prefix('banks')->group(function() {
        Route::get('/', [BankController::class, 'index'])->name('banks');
        Route::post('/create', [BankController::class, 'create']);
        Route::post('/{id}/update', [BankController::class, 'update']);
        Route::delete('/{id}', [BankController::class, 'delete']);
    });

    Route::prefix('cashless-providers')->group(function() {
        Route::get('/', [CashlessProviderController::class, 'index'])->name('cashless-providers');
        Route::post('/create', [CashlessProviderController::class, 'create']);
        Route::post('/{id}/update', [CashlessProviderController::class, 'update']);
        Route::delete('/{id}', [CashlessProviderController::class, 'delete']);
    });

    Route::prefix('cashless-terminals')->group(function() {
        Route::get('/', [CashlessTerminalController::class, 'index'])->name('cashless-terminals');
        Route::post('/create', [CashlessTerminalController::class, 'create']);
        Route::post('/{id}/update', [CashlessTerminalController::class, 'update']);
        Route::delete('/{id}', [CashlessTerminalController::class, 'delete']);
    });

    Route::prefix('categories')->group(function() {
        Route::get('/', [CategoryController::class, 'index'])->name('categories');
        Route::post('/create', [CategoryController::class, 'create']);
        Route::post('/{id}/update', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'delete']);
    });

    Route::prefix('category-groups')->group(function() {
        Route::get('/', [CategoryGroupController::class, 'index'])->name('category-groups');
        Route::post('/create', [CategoryGroupController::class, 'create']);
        Route::post('/{id}/update', [CategoryGroupController::class, 'update']);
        Route::delete('/{id}', [CategoryGroupController::class, 'delete']);
    });

    Route::prefix('countries')->group(function() {
        Route::get('/', [CountryController::class, 'index'])->name('countries');
        Route::post('/create', [CountryController::class, 'create']);
        Route::post('/{id}/update', [CountryController::class, 'update']);
        Route::post('/{id}/exchange-rate', [CountryController::class, 'updateExchangeRate']);
        Route::delete('/{id}', [CountryController::class, 'delete']);
    });

    Route::prefix('customers')->group(function() {
        Route::get('/', [CustomerController::class, 'index'])->name('customers');
        Route::post('/create', [CustomerController::class, 'create']);
        Route::post('/{id}/update', [CustomerController::class, 'update']);
        Route::delete('/{id}', [CustomerController::class, 'delete']);
    });

    Route::prefix('payment-methods')->group(function() {
        Route::get('/', [PaymentMethodController::class, 'index'])->name('payment-methods');
        Route::post('/create', [PaymentMethodController::class, 'create']);
        Route::post('/{id}/update', [PaymentMethodController::class, 'update']);
        Route::delete('/{id}', [PaymentMethodController::class, 'delete']);
    });

    Route::prefix('payment-terms')->group(function() {
        Route::get('/', [PaymentTermController::class, 'index'])->name('payment-terms');
        Route::post('/create', [PaymentTermController::class, 'create']);
        Route::post('/{id}/update', [PaymentTermController::class, 'update']);
        Route::delete('/{id}', [PaymentTermController::class, 'delete']);
    });

    Route::prefix('products')->group(function() {
        Route::get('/', [ProductController::class, 'index'])->name('products');
        Route::get('/unit-costs', [ProductController::class, 'unitCostIndex'])->name('unit-costs');
        Route::post('/{id}/toggle-activate-deactivate', [ProductController::class, 'toggleActivateDeactivate']);
        Route::post('/{id}/uom-binding', [ProductController::class, 'bindUom']);
        Route::delete('/product-uoms/{productUomId}', [ProductController::class, 'deleteProductUom']);
        Route::post('/create', [ProductController::class, 'create']);
        Route::post('/{id}/update', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'delete']);
    });

    Route::prefix('profiles')->group(function() {
        Route::get('/', [ProfileController::class, 'index'])->name('profiles');
        Route::post('/create', [ProfileController::class, 'create']);
        Route::post('/{id}/update', [ProfileController::class, 'update']);
        Route::delete('/{id}', [ProfileController::class, 'delete']);
    });

    Route::prefix('permissions')->group(function() {
        Route::get('/', [RolePermissionController::class, 'indexPermission'])->name('permissions');
        Route::post('/create', [RolePermissionController::class, 'createPermission']);
        Route::post('/{id}/update', [RolePermissionController::class, 'updatePermission']);
        Route::delete('/{id}', [RolePermissionController::class, 'deletePermission']);
    });

    Route::prefix('simcards')->group(function() {
        Route::get('/', [SimcardController::class, 'index'])->name('simcards');
        Route::post('/create', [SimcardController::class, 'create']);
        Route::post('/{id}/update', [SimcardController::class, 'update']);
        Route::delete('/{id}', [SimcardController::class, 'delete']);
    });

    Route::prefix('statuses')->group(function() {
        Route::get('/', [StatusController::class, 'index'])->name('statuses');
        Route::post('/create', [StatusController::class, 'create']);
        Route::post('/{id}/update', [StatusController::class, 'update']);
        Route::delete('/{id}', [StatusController::class, 'delete']);
    });

    Route::prefix('tags')->group(function() {
        Route::get('/', [TagController::class, 'index'])->name('tags');
        Route::post('/create', [TagController::class, 'create']);
        Route::post('/{id}/update', [TagController::class, 'update']);
        Route::delete('/{id}', [TagController::class, 'delete']);
    });

    Route::prefix('taxes')->group(function() {
        Route::get('/', [TaxController::class, 'index'])->name('taxes');
        Route::post('/create', [TaxController::class, 'create']);
        Route::post('/{id}/update', [TaxController::class, 'update']);
        Route::delete('/{id}', [TaxController::class, 'delete']);
    });

    Route::prefix('telcos')->group(function() {
        Route::get('/', [TelcoController::class, 'index'])->name('telcos');
        Route::post('/create', [TelcoController::class, 'create']);
        Route::post('/{id}/update', [TelcoController::class, 'update']);
        Route::delete('/{id}', [TelcoController::class, 'delete']);
    });

    Route::prefix('roles')->group(function() {
        Route::get('/', [RolePermissionController::class, 'indexRole'])->name('roles');
        Route::post('/create', [RolePermissionController::class, 'createRole']);
        Route::post('/{id}/update', [RolePermissionController::class, 'updateRole']);
        Route::delete('/{id}', [RolePermissionController::class, 'deleteRole']);
    });

    Route::prefix('self')->group(function() {
        Route::get('/', [UserController::class, 'selfIndex'])->name('self');
        Route::post('/{id}/update', [UserController::class, 'selfUpdate']);
    });

    Route::prefix('uoms')->group(function() {
        Route::get('/', [UomController::class, 'index'])->name('uoms');
        Route::post('/create', [UomController::class, 'create']);
        Route::post('/{id}/update', [UomController::class, 'update']);
        Route::delete('/{id}', [UomController::class, 'delete']);
    });

    Route::prefix('users')->group(function() {
        Route::get('/', [UserController::class, 'index'])->name('users');
        Route::post('/create', [UserController::class, 'create']);
        Route::post('/{id}/update', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'delete']);
    });

    Route::prefix('vends')->group(function() {
        Route::get('/', [VendController::class, 'index'])->name('vends');
        Route::get('/{id}/temp/{type}', [VendController::class, 'temp'])->name('temp');
        Route::get('/transactions', [VendController::class, 'transactionIndex'])->name('vends-transactions');
        Route::get('/transactions/excel', [VendController::class, 'exportTransactionExcel']);
        Route::get('/channel-error-logs-email', [VendController::class, 'channelErrorLogsEmail']);
    });

    Route::prefix('zones')->group(function() {
        Route::get('/', [ZoneController::class, 'index'])->name('zones');
        Route::post('/create', [ZoneController::class, 'create']);
        Route::post('/{id}/update', [ZoneController::class, 'update']);
        Route::delete('/{id}', [ZoneController::class, 'delete']);
    });

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
});

require __DIR__.'/auth.php';
