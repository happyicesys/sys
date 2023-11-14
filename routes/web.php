<?php

use App\Http\Controllers\Api\V1\VendDataController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\CashlessProviderController;
use App\Http\Controllers\CashlessTerminalController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryGroupController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryPlatformController;
use App\Http\Controllers\DeliveryPlatformOrderController;
use App\Http\Controllers\DeliveryProductMappingController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LocationTypeController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\OauthController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PaymentTermController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductMappingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResourceCenterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
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
use App\Http\Controllers\VendChannelErrorController;
use App\Http\Controllers\VendCriteriaController;
use App\Http\Controllers\VendCriteriaBindingController;
use App\Http\Controllers\ZoneController;
use Carbon\Carbon;
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

    return redirect('/login');
    // return Inertia::render('Dashboard', [
    //     'canLogin' => Route::has('login'),
    //     'canRegister' => Route::has('register'),
    //     'laravelVersion' => Application::VERSION,
    //     'phpVersion' => PHP_VERSION,
    // ]);
});

Route::post('/SetPara2', [VendDataController::class, 'create']);

Route::middleware(['auth', 'cors'])->group(function() {

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

    Route::prefix('dashboard')->group(function() {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    });

    Route::prefix('delivery-platform-orders')->group(function() {
        Route::get('/', [DeliveryPlatformOrderController::class, 'index'])->name('delivery-platform-orders');
        Route::get('/{id}/edit', [DeliveryPlatformOrderController::class, 'edit'])->name('delivery-platform-orders.edit');
        Route::post('/{id}/request-cancel-order', [DeliveryPlatformOrderController::class, 'requestCancelOrder']);
    });

    Route::prefix('delivery-product-mappings')->group(function() {
        Route::get('/', [DeliveryProductMappingController::class, 'index'])->name('delivery-product-mappings');
        Route::get('/create', [DeliveryProductMappingController::class, 'create'])->name('delivery-product-mappings.create');
        Route::post('/{id}/bind-vend', [DeliveryProductMappingController::class, 'bindVend']);
        Route::get('/{id}/edit', [DeliveryProductMappingController::class, 'edit'])->name('delivery-product-mappings.edit');
        Route::delete('/{id}', [DeliveryProductMappingController::class, 'delete']);
        Route::post('/store', [DeliveryProductMappingController::class, 'store']);
        Route::post('/{id}/update', [DeliveryProductMappingController::class, 'update']);
        Route::delete('/unbind/{deliveryProductMappingVendId}', [DeliveryProductMappingController::class, 'unbindVend']);
        Route::post('/{id}/toggle-pause-all-vends', [DeliveryProductMappingController::class, 'togglePauseAllVends']);
        Route::post('/vends/{deliveryProductMappingVendId}/toggle-pause-vend', [DeliveryProductMappingController::class, 'togglePauseVend']);
        Route::post('/channels/{channelId}/toggle-pause', [DeliveryProductMappingController::class, 'togglePauseChannel']);
        Route::post('/channels/{channelId}/update', [DeliveryProductMappingController::class, 'updateChannel']);
    });

    Route::prefix('delivery-product-mapping-items')->group(function() {
        Route::delete('/{id}', [DeliveryProductMappingController::class, 'deleteDeliveryProductMappingItem']);
        Route::post('/delivery-product-mapping/{id}/store', [DeliveryProductMappingController::class, 'storeDeliveryProductMappingItem']);
        Route::post('/{id}/toggle-pause', [DeliveryProductMappingController::class, 'togglePauseDeliveryProductMappingItem']);
    });

    Route::prefix('delivery-platform-operators')->group(function() {
        Route::delete('/{id}', [OperatorController::class, 'deleteDeliveryPlatformOperator']);
        Route::post('/operator/{id}/store', [OperatorController::class, 'storeDeliveryPlatformOperator']);
    });

    Route::prefix('vend-criterias')->group(function() {
        Route::get('/', [VendCriteriaController::class, 'index'])->name('vend-criterias');
        Route::post('/{id}/update', [VendCriteriaController::class, 'update']);
    });

    Route::prefix('vend-criteria-bindings')->group(function() {
        Route::get('/', [VendCriteriaBindingController::class, 'index'])->name('vend-criteria-bindings');
        Route::post('/create', [VendCriteriaBindingController::class, 'create']);
        Route::post('/{id}/update', [VendCriteriaBindingController::class, 'update']);
        Route::delete('/{id}', [VendCriteriaBindingController::class, 'delete']);
    });

    Route::prefix('customers')->group(function() {
        Route::get('/', [CustomerController::class, 'index'])->name('customers');
        Route::post('/create', [CustomerController::class, 'create']);
        Route::post('/{id}/update', [CustomerController::class, 'update']);
        Route::delete('/{id}', [CustomerController::class, 'delete']);
        Route::get('/sync-next-delivery-date', [CustomerController::class, 'syncNextDeliveryDate']);
    });

    Route::prefix('holidays')->group(function() {
        Route::get('/', [HolidayController::class, 'index'])->name('holidays');
        Route::post('/create', [HolidayController::class, 'create']);
        Route::post('/{id}/update', [HolidayController::class, 'update']);
        Route::delete('/{id}', [HolidayController::class, 'delete']);
    });

    Route::prefix('maps')->group(function() {
        Route::get('/', [MapController::class, 'index'])->name('maps');
    });

    Route::prefix('oauth-clients')->group(function() {
        Route::get('/', [OauthController::class, 'index'])->name('oauth-clients');
    });

    Route::prefix('operators')->group(function() {
        Route::get('/', [OperatorController::class, 'index'])->name('operators');
        Route::get('/create', [OperatorController::class, 'create']);
        Route::post('/store', [OperatorController::class, 'store']);
        Route::get('/{id}/edit', [OperatorController::class, 'edit'])->name('operators.edit');
        Route::post('/{id}/update', [OperatorController::class, 'update']);
        Route::delete('/{id}', [OperatorController::class, 'delete']);
        Route::post('/bind-vend', [OperatorController::class, 'bindVend']);
        Route::post('/unbind-vend', [OperatorController::class, 'unbindVend']);
        Route::post('/{id}/delivery-platform/create', [OperatorController::class, 'bindDeliveryPlatform']);
        Route::delete('/delivery-platform/{delivery_platform_operator_id}', [OperatorController::class, 'unbindDeliveryPlatform']);
    });

    Route::prefix('operator-payment-gateways')->group(function() {
        Route::delete('/{id}', [OperatorController::class, 'deleteOperatorPaymentGateway']);
        Route::post('/operator/{id}/store', [OperatorController::class, 'storeOperatorPaymentGateway']);
    });

    Route::prefix('operator-vends')->group(function() {
        Route::delete('/{id}', [OperatorController::class, 'deleteOperatorVend']);
        Route::post('/store', [OperatorController::class, 'bindVend']);
    });

    Route::prefix('reports')->group(function() {
        Route::get('/sales/{type}/excel', [ReportController::class, 'exportSalesExcel']);
        Route::get('/sales/{type}', [ReportController::class, 'indexSales']);


        Route::get('/gp/vend', [ReportController::class, 'indexGpVm']);
        Route::get('/gp/product', [ReportController::class, 'indexGpProduct']);
        Route::get('/gp/category', [ReportController::class, 'indexGpCategory']);
        Route::get('/gp/location-type', [ReportController::class, 'indexGpLocationType']);
        Route::get('/gp/vend/excel', [ReportController::class, 'exportUnitCostVendExcel']);
        Route::get('/gp/product/excel', [ReportController::class, 'exportUnitCostProductExcel']);
        Route::get('/gp/category/excel', [ReportController::class, 'exportUnitCostCategoryExcel']);
        Route::get('/gp/location-type/excel', [ReportController::class, 'exportUnitCostLocationTypeExcel']);

        Route::get('/stock-count', [ReportController::class, 'indexStockCount']);
        Route::get('/stock-count/excel', [ReportController::class, 'exportStockCountChannelExcel']);
    });

    Route::prefix('resource-centers')->group(function() {
        Route::get('/', [ResourceCenterController::class, 'index'])->name('resource-centers');
        Route::post('/create', [ResourceCenterController::class, 'create']);
        Route::post('/{id}/update', [ResourceCenterController::class, 'update']);
        Route::delete('/{id}', [ResourceCenterController::class, 'delete']);
    });

    Route::prefix('location-types')->group(function() {
        Route::get('/', [LocationTypeController::class, 'index'])->name('location-types');
        Route::post('/create', [LocationTypeController::class, 'create']);
        Route::post('/{id}/update', [LocationTypeController::class, 'update']);
        Route::delete('/{id}', [LocationTypeController::class, 'delete']);
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

    Route::prefix('product-mappings')->group(function() {
        Route::get('/', [ProductMappingController::class, 'index'])->name('product-mappings');
        Route::post('/create', [ProductMappingController::class, 'create']);
        Route::post('/{id}/update', [ProductMappingController::class, 'update']);
        Route::post('/{id}/update/vends', [ProductMappingController::class, 'bindVends']);
        Route::delete('/{id}', [ProductMappingController::class, 'delete']);
        Route::post('/replicate', [ProductMappingController::class, 'replicate']);
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

    Route::prefix('settings')->group(function() {
        Route::get('/', [SettingController::class, 'index'])->name('settings');
        Route::get('/vend/{id}/{type}', [SettingController::class, 'editOrCreate'])->name('settings.edit');
        Route::post('/{id}/toggle-activation', [SettingController::class, 'toggleActivation']);
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
        Route::post('/bind-vend', [UserController::class, 'bindVend']);
        Route::post('/unbind-vend', [UserController::class, 'unbindVend']);
    });

    Route::prefix('vends')->group(function() {
        Route::post('/create', [VendController::class, 'create']);
        Route::get('/channels/excel', [VendController::class, 'exportChannelExcel']);
        Route::get('/', [VendController::class, 'index'])->name('vends');
        Route::get('/{id}/temp/{type}', [VendController::class, 'temp'])->name('temp');
        Route::get('/{id}/temp/{type}/excel', [VendController::class, 'exportTempExcel']);
        Route::get('/transactions', [VendController::class, 'transactionIndex'])->name('vends-transactions');
        Route::get('/transactions/excel', [VendController::class, 'exportTransactionExcel']);
        Route::get('/vend-snapshots/excel/{vendSnapshotId}', [VendController::class, 'exportVendSnapshotExcel']);
        Route::get('/channel-error-logs-email', [VendController::class, 'channelErrorLogsEmail']);
        Route::post('/{id}/update', [VendController::class, 'update']);
        Route::post('/{id}/unbind', [VendController::class, 'unbindCustomer']);
        Route::post('/{id}/edit-products', [VendController::class, 'editProducts']);
        Route::post('/{id}/dispense-product', [VendController::class, 'dispenseProduct']);
    });

    Route::prefix('vend-channel-errors')->group(function() {
        Route::get('/', [VendChannelErrorController::class, 'index'])->name('vend-channel-errors');
        Route::post('/create', [VendChannelErrorController::class, 'create']);
        Route::post('/{id}/update', [VendChannelErrorController::class, 'update']);
        Route::delete('/{id}', [VendChannelErrorController::class, 'delete']);
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
