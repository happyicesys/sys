<?php

use App\Http\Controllers\Api\V1\VendDataController;
use App\Http\Controllers\ApkSettingController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CardTerminalController;
use App\Http\Controllers\CashlessTerminalController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryGroupController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryPlatformCampaignController;
use App\Http\Controllers\DeliveryPlatformController;
use App\Http\Controllers\DeliveryPlatformOrderController;
use App\Http\Controllers\DeliveryProductMappingController;
use App\Http\Controllers\DeliveryProductMappingVendController;
use App\Http\Controllers\DeliveryPlatformRefNumberController;
use App\Http\Controllers\HidCardController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\KeyController;
use App\Http\Controllers\LocationTypeController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\ModemTypeController;
use App\Http\Controllers\ModemUnitController;
use App\Http\Controllers\OauthController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\OpsJobController;
use App\Http\Controllers\OpsJobTaskController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductMovementController;
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
use App\Http\Controllers\TutorialController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OpsPerformanceController;
use App\Http\Controllers\VendController;
use App\Http\Controllers\VendConfigController;
use App\Http\Controllers\VendContractController;
use App\Http\Controllers\VendChannelErrorController;
use App\Http\Controllers\VendCriteriaController;
use App\Http\Controllers\VendCriteriaBindingController;
use App\Http\Controllers\VendModelController;
use App\Http\Controllers\VendPrefixController;
use App\Http\Controllers\VendSerialNumberController;
use App\Http\Controllers\VendAlertParameterController;
use App\Http\Controllers\VoucherController;
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

Route::get('/client/api-docs', function () {
    return Inertia::render('Client/ApiDocs');
});

Route::middleware(['auth', 'cors'])->group(function () {

    Route::prefix('apk-settings')->group(function () {
        Route::get('/', [ApkSettingController::class, 'index'])->name('apk-settings');
        Route::get('/create', [ApkSettingController::class, 'create']);
        Route::post('/{id}/campaigns/bind', [ApkSettingController::class, 'bindCampaigns']);
        Route::delete('/{id}/campaigns/{campaignId}', [ApkSettingController::class, 'unbindCampaign']);
        Route::post('{id}/create-campaign-item', [ApkSettingController::class, 'createCampaignItem']);
        Route::delete('/campaign-items/{id}/delete-campaign-item', [ApkSettingController::class, 'deleteCampaignItem']);
        Route::get('/{id}/edit', [ApkSettingController::class, 'edit'])->name('apk-settings.edit');
        Route::post('/{id}/push', [ApkSettingController::class, 'push']);
        Route::post('/{id}/update', [ApkSettingController::class, 'update']);
        Route::post('/store', [ApkSettingController::class, 'store']);
        Route::post('/{id}/upload-campaign-images', [ApkSettingController::class, 'uploadCampaignImages']);
        Route::post('/{id}/upload-campaign-videos', [ApkSettingController::class, 'uploadCampaignVideos']);
        Route::post('/{id}/upload-images', [ApkSettingController::class, 'uploadImages']);
        Route::post('/{id}/upload-videos', [ApkSettingController::class, 'uploadVideos']);
        Route::delete('/unbind-vend/{vendId}', [ApkSettingController::class, 'unbindVend']);
        Route::delete('/{id}', [ApkSettingController::class, 'destroy']);
    });

    Route::prefix('attachments')->group(function () {
        Route::get('/', [AttachmentController::class, 'index'])->name('attachments');
        Route::post('/create', [AttachmentController::class, 'create']);
        Route::post('/{id}/update', [AttachmentController::class, 'update']);
        Route::delete('/{id}', [AttachmentController::class, 'delete']);
    });

    Route::prefix('card-terminals')->group(function () {
        Route::get('/', [CardTerminalController::class, 'index'])->name('card-terminals');
        Route::post('/create', [CardTerminalController::class, 'create']);
        Route::post('/{id}/update', [CardTerminalController::class, 'update']);
        Route::delete('/{id}', [CardTerminalController::class, 'delete']);
    });

    Route::prefix('cashless-terminals')->group(function () {
        Route::get('/', [CashlessTerminalController::class, 'index'])->name('cashless-terminals');
        Route::post('/store', [CashlessTerminalController::class, 'store']);
        Route::post('/{id}/update', [CashlessTerminalController::class, 'update']);
        Route::delete('/{id}', [CashlessTerminalController::class, 'delete']);
    });

    Route::prefix('campaigns')->group(function () {
        Route::get('/', [CampaignController::class, 'index'])->name('campaigns');
        Route::get('/create', [CampaignController::class, 'createView'])->name('campaigns.create');
        Route::post('/create', [CampaignController::class, 'create']);
        Route::get('/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
        Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
        Route::post('/{campaign}/update', [CampaignController::class, 'update']);
        Route::delete('/{id}', [CampaignController::class, 'delete']);
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('categories');
        Route::post('/create', [CategoryController::class, 'create']);
        Route::post('/{id}/update', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'delete']);
    });


    Route::prefix('category-groups')->group(function () {
        Route::get('/', [CategoryGroupController::class, 'index'])->name('category-groups');
        Route::post('/create', [CategoryGroupController::class, 'create']);
        Route::post('/{id}/update', [CategoryGroupController::class, 'update']);
        Route::delete('/{id}', [CategoryGroupController::class, 'delete']);
    });

    Route::prefix('countries')->group(function () {
        Route::get('/', [CountryController::class, 'index'])->name('countries');
        Route::post('/create', [CountryController::class, 'create']);
        Route::post('/{id}/update', [CountryController::class, 'update']);
        Route::post('/{id}/exchange-rate', [CountryController::class, 'updateExchangeRate']);
        Route::delete('/{id}', [CountryController::class, 'delete']);
    });

    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('customers');
        Route::get('/summary', [CustomerController::class, 'summary'])->name('customers.summary');
        Route::get('/summary/excel', [CustomerController::class, 'summaryExportExcel'])->name('customers.summary.excel');
        // Action-triggered lock / unlock for a single Customer Summary row
        // (by customer_period_summaries.id). Lock = admin-access customers;
        // unlock is gated to superadmin/admin in the controller.
        Route::post('/summary/{id}/lock', [CustomerController::class, 'lockCustomerPeriodSummary'])
            ->name('customers.summary.lock');
        Route::post('/summary/{id}/unlock', [CustomerController::class, 'unlockCustomerPeriodSummary'])
            ->name('customers.summary.unlock');
        // Paid / Unpaid for a locked Customer Summary row. Paid = same
        // permission as Lock (admin-access customers); Unpaid = same as
        // Unlock (superadmin / admin) since it reverses a recorded action.
        // Unlock is server-blocked when paid_at IS NOT NULL — the user has
        // to Unpaid first (the UI also disables the Unlock button).
        Route::post('/summary/{id}/paid', [CustomerController::class, 'markPaidCustomerPeriodSummary'])
            ->name('customers.summary.paid');
        Route::post('/summary/{id}/unpaid', [CustomerController::class, 'markUnpaidCustomerPeriodSummary'])
            ->name('customers.summary.unpaid');
        // Performance Report email send (button on Customer Summary > Action).
        // Currently a stub — the actual queued send is wired in a follow-up.
        Route::post('/{id}/send-performance-report', [CustomerController::class, 'sendPerformanceReport'])
            ->name('customers.send-performance-report');
        // JSON endpoint backing the "Report Content" preview modal in the
        // Action column. Returns the same structured payload that will drive
        // the email body once the queued send is wired.
        Route::get('/{id}/performance-report-content', [CustomerController::class, 'getPerformanceReportContent'])
            ->name('customers.performance-report-content');
        // Customer Summary > Action ▸ "Create API Invoice" (single + bulk).
        // Mirrors OpsJob's syncCmsInvoices() flow but for period summaries —
        // dispatches SyncCustomerInvoiceCMS to POST to the CMS deals endpoint
        // using hardcoded item codes (055/V01/60) per contract type.
        Route::post('/{id}/cms-invoices', [CustomerController::class, 'syncCmsInvoice'])
            ->name('customers.cms-invoice.create');
        Route::post('/cms-invoices/bulk', [CustomerController::class, 'syncCmsInvoicesBulk'])
            ->name('customers.cms-invoice.bulk');
        Route::get('/{id}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::get('/create', [CustomerController::class, 'create']);
        Route::post('/store', [CustomerController::class, 'store']);
        Route::post('/{id}/update', [CustomerController::class, 'update']);
        Route::post('/{id}/toggle-activation', [CustomerController::class, 'toggleActivation']);
        Route::delete('/{id}', [CustomerController::class, 'delete']);
        Route::get('/excel', [CustomerController::class, 'exportExcel']);
        Route::get('/sync-next-delivery-date', [CustomerController::class, 'syncNextDeliveryDate']);
        Route::post('/{id}/upload-attachments', [CustomerController::class, 'uploadAttachment']);
        Route::post('/{id}/upload-photos', [CustomerController::class, 'uploadPhoto']);
        Route::post('/{id}/upload-contracts', [CustomerController::class, 'uploadContract']);
        Route::post('/{id}/bind-vend', [CustomerController::class, 'bindVend']);
        Route::get('/{id}/selling-prices/type/{type}', [CustomerController::class, 'getProductSellingPrices']);
        Route::post('/sync-cms-invoice-items', [CustomerController::class, 'syncCmsInvoiceItems']);
        Route::post('/{id}/disconnect-cms', [CustomerController::class, 'disconnectCms']);
        // Customer-level notes — edited inline on the Customer Summary page
        // (Customer Tag column). Mirrors products-availability.update-remarks.
        Route::post('/{id}/update-notes', [CustomerController::class, 'updateNotes'])->name('customers.update-notes');
        // Ops-side free-text note (refilling/operations) — edited inline on
        // Vend/CustomerIndex "Refilling Routes" column. Same shape as
        // update-notes; lives under the same /customers prefix because it
        // writes a column on the customer record.
        Route::post('/{id}/update-ops-note', [CustomerController::class, 'updateOpsNote'])->name('customers.update-ops-note');
        Route::post('/map', [CustomerController::class, 'getMap']);
    });

    Route::prefix('dashboard')->group(function () {
        Route::redirect('/', '/dashboard/performance');
        Route::get('/performance', [DashboardController::class, 'index'])->name('dashboard');
        // Lightweight JSON used by the post-login "This month sales" popup (HIPL group only).
        Route::get('/monthly-sales-popup', [DashboardController::class, 'monthlySalesPopup'])->name('dashboard.monthly-sales-popup');
    });

    Route::prefix('delivery-platform-campaigns')->group(function () {
        Route::get('/', [DeliveryPlatformCampaignController::class, 'index'])->name('delivery-platform-campaigns');
        Route::get('/create', [DeliveryPlatformCampaignController::class, 'create']);
        Route::get('/{id}/edit', [DeliveryPlatformCampaignController::class, 'edit'])->name('delivery-platform-campaigns.edit');
        Route::post('/store', [DeliveryPlatformCampaignController::class, 'store']);
        Route::post('/{id}/create-item', [DeliveryPlatformCampaignController::class, 'createItem']);
        Route::post('/{id}/submit-platform', [DeliveryPlatformCampaignController::class, 'submitPlatform']);
        Route::post('/{id}/item-vend', [DeliveryPlatformCampaignController::class, 'createItemVend']);
        Route::post('/{id}/batch-item-vend', [DeliveryPlatformCampaignController::class, 'batchCreateItemVend']);
        Route::delete('/item/{deliveryPlatformCampaignItemID}', [DeliveryPlatformCampaignController::class, 'deleteItem']);
        Route::delete('/item-vend/{delPlaCamItemVendID}', [DeliveryPlatformCampaignController::class, 'deleteItemVend']);
        Route::delete('/{id}', [DeliveryPlatformCampaignController::class, 'destroy']);
    });
    Route::prefix('delivery-platform-orders')->group(function () {
        Route::get('/', [DeliveryPlatformOrderController::class, 'index'])->name('delivery-platform-orders');
        Route::get('/excel', [DeliveryPlatformOrderController::class, 'exportExcel']);
        Route::get('/{id}/edit', [DeliveryPlatformOrderController::class, 'edit'])->name('delivery-platform-orders.edit');
        Route::post('/{id}/request-cancel-order', [DeliveryPlatformOrderController::class, 'requestCancelOrder']);
    });

    Route::prefix('delivery-product-mappings')->group(function () {
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
        Route::post('/{id}/save-bundle-sales', [DeliveryProductMappingController::class, 'saveBundleSales']);
        Route::delete('/bulks/{deliveryProductMappingBulkID}', [DeliveryProductMappingController::class, 'deleteDeliveryProductMappingBulk']);
    });

    Route::prefix('delivery-product-mapping-items')->group(function () {
        Route::delete('/{id}', [DeliveryProductMappingController::class, 'deleteDeliveryProductMappingItem']);
        Route::post('/delivery-product-mapping/{id}/store', [DeliveryProductMappingController::class, 'storeDeliveryProductMappingItem']);
        Route::post('/{id}/update', [DeliveryProductMappingController::class, 'updateDeliveryProductMappingItem']);
        Route::post('/{id}/toggle-pause', [DeliveryProductMappingController::class, 'togglePauseDeliveryProductMappingItem']);
    });

    Route::prefix('delivery-product-mapping-vends')->group(function () {
        Route::get('/', [DeliveryProductMappingVendController::class, 'index'])->name('delivery-product-mapping-vends');
    });

    Route::prefix('delivery-platform-ref-numbers')->group(function () {
        Route::get('/', [DeliveryPlatformRefNumberController::class, 'index'])->name('delivery-platform-ref-numbers');
        Route::get('/create', [DeliveryPlatformRefNumberController::class, 'create'])->name('delivery-platform-ref-numbers.create');
        Route::post('/', [DeliveryPlatformRefNumberController::class, 'store'])->name('delivery-platform-ref-numbers.store');
        Route::get('/{id}/edit', [DeliveryPlatformRefNumberController::class, 'edit'])->name('delivery-platform-ref-numbers.edit');
        Route::post('/{id}/update', [DeliveryPlatformRefNumberController::class, 'update'])->name('delivery-platform-ref-numbers.update');
    });

    Route::prefix('delivery-platform-operators')->group(function () {
        Route::delete('/{id}', [OperatorController::class, 'deleteDeliveryPlatformOperator']);
        Route::post('/operator/{id}/store', [OperatorController::class, 'storeDeliveryPlatformOperator']);
    });

    Route::prefix('keys')->group(function () {
        Route::get('/', [KeyController::class, 'index'])->name('keys');
        Route::post('/store', [KeyController::class, 'store']);
        Route::post('/{id}/update', [KeyController::class, 'update']);
        Route::delete('/{id}', [KeyController::class, 'delete']);
    });

    Route::prefix('vend-criterias')->group(function () {
        Route::get('/', [VendCriteriaController::class, 'index'])->name('vend-criterias');
        Route::post('/{id}/update', [VendCriteriaController::class, 'update']);
    });

    Route::prefix('vend-criteria-bindings')->group(function () {
        Route::get('/', [VendCriteriaBindingController::class, 'index'])->name('vend-criteria-bindings');
        Route::post('/create', [VendCriteriaBindingController::class, 'create']);
        Route::post('/{id}/update', [VendCriteriaBindingController::class, 'update']);
        Route::delete('/{id}', [VendCriteriaBindingController::class, 'delete']);
    });

    Route::prefix('hid-cards')->group(function () {
        Route::get('/', [HidCardController::class, 'index'])->name('hid-cards'); // List

        Route::get('/create', [HidCardController::class, 'create'])->name('hid-cards.create'); // Form page
        Route::post('/', [HidCardController::class, 'store'])->name('hid-cards.store'); // Form submit

        Route::get('/{id}/edit', [HidCardController::class, 'edit'])->name('hid-cards.edit'); // Edit page
        Route::get('/csv', [HidCardController::class, 'exportCsv']); // Export CSV
        Route::get('/excel', [HidCardController::class, 'exportExcel']);
        Route::post('/{id}/update', [HidCardController::class, 'update'])->name('hid-cards.update'); // Edit submit

        Route::delete('/{id}', [HidCardController::class, 'delete'])->name('hid-cards.delete'); // Delete
    });


    Route::prefix('holidays')->group(function () {
        Route::get('/', [HolidayController::class, 'index'])->name('holidays');
        Route::post('/create', [HolidayController::class, 'create']);
        Route::post('/{id}/update', [HolidayController::class, 'update']);
        Route::delete('/{id}', [HolidayController::class, 'delete']);
    });

    Route::prefix('maps')->group(function () {
        Route::get('/', [MapController::class, 'index'])->name('maps');
        Route::get('/search', [MapController::class, 'search'])->name('maps.search');
    });

    Route::prefix('oauth-clients')->group(function () {
        Route::get('/', [OauthController::class, 'index'])->name('oauth-clients');
    });

    Route::prefix('operators')->group(function () {
        Route::get('/', [OperatorController::class, 'index'])->name('operators');
        Route::get('/create', [OperatorController::class, 'create']);
        Route::post('/store', [OperatorController::class, 'store']);
        Route::get('/{id}/edit', [OperatorController::class, 'edit'])->name('operators.edit');
        Route::post('/{id}/update', [OperatorController::class, 'update']);
        Route::delete('/{id}', [OperatorController::class, 'delete']);
        Route::post('/bind-customer', [OperatorController::class, 'bindCustomer']);
        Route::post('/bind-vend', [OperatorController::class, 'bindVend']);
        Route::post('/unbind-customer', [OperatorController::class, 'unbindCustomer']);
        Route::post('/unbind-vend', [OperatorController::class, 'unbindVend']);
        Route::post('/{id}/delivery-platform/create', [OperatorController::class, 'bindDeliveryPlatform']);
        Route::delete('/delivery-platform/{delivery_platform_operator_id}', [OperatorController::class, 'unbindDeliveryPlatform']);
        Route::post('/{id}/callbacks', [OperatorController::class, 'storeOperatorCallback'])->name('operators.store-callback');
        Route::delete('/callbacks/{id}', [OperatorController::class, 'deleteOperatorCallback'])->name('operators.delete-callback');
    });

    Route::prefix('operator-payment-gateways')->group(function () {
        Route::delete('/{id}', [OperatorController::class, 'deleteOperatorPaymentGateway']);
        Route::post('/operator/{id}/store', [OperatorController::class, 'storeOperatorPaymentGateway']);
    });

    Route::prefix('operator-vends')->group(function () {
        Route::delete('/{id}', [OperatorController::class, 'deleteOperatorVend']);
        Route::post('/store', [OperatorController::class, 'bindVend']);
    });

    Route::prefix('ops-jobs')->group(function () {
        Route::get('/summary', [OpsJobController::class, 'summary'])->name('ops-jobs.summary');
        Route::get('/', [OpsJobController::class, 'index'])->name('ops-jobs');
        Route::get('/create', [OpsJobController::class, 'create']);
        Route::get('/{id}/edit', [OpsJobController::class, 'edit'])->name('ops-jobs.edit');
        Route::post('/store', [OpsJobController::class, 'store']);
        Route::post('/{id}/update', [OpsJobController::class, 'update']);
        Route::post('/{id}/update/stock-action', [OpsJobController::class, 'updateJobStockAction']);
        Route::delete('/{id}', [OpsJobController::class, 'delete']);
        Route::post('/{id}/complete', [OpsJobController::class, 'complete']);
        Route::post('/items/{itemID}/status', [OpsJobController::class, 'changeItemStatus']);
        Route::post('/items/{itemID}/undo-status', [OpsJobController::class, 'undoItemStatus']);
        Route::post('/{id}/pick', [OpsJobController::class, 'pick']);
        Route::post('/{id}/deliver', [OpsJobController::class, 'deliver']);
        Route::post('/{id}/renumber', [OpsJobController::class, 'renumberItems']);
        Route::get('/{id}/route', [OpsJobController::class, 'route']);
        Route::post('/{id}/sort', [OpsJobController::class, 'sortItems']);
        Route::post('/assign', [OpsJobController::class, 'assign']);
        Route::post('/{id}/item/create', [OpsJobController::class, 'createItem']);
        Route::post('/items/batch-update', [OpsJobController::class, 'batchUpdateItems']);
        Route::post('/items/{itemId}/update', [OpsJobController::class, 'updateItem']);
        Route::post('/items/{itemId}/update/remarks', [OpsJobController::class, 'updateItemRemarks']);
        Route::post('/items/{itemId}/update/stock-action', [OpsJobController::class, 'updateStockAction']);
        Route::post('/items/{itemId}/undo-stock-action', [OpsJobController::class, 'undoStockAction']);
        Route::post('/items/{itemID}/toggle/is-ignore-limit', [OpsJobController::class, 'toggleIsIgnoreLimit']);
        Route::post('/{id}/create-cms-empty-invoices', [OpsJobController::class, 'createCmsEmptyInvoices']);
        Route::post('/{id}/sync-cms-invoices', [OpsJobController::class, 'syncCmsInvoices']);
        Route::post('/{id}/sync-inventory', [OpsJobController::class, 'syncInventory']);
        Route::delete('/items/{itemId}', [OpsJobController::class, 'deleteItem']);
        Route::get('/items/{itemID}/edit', [OpsJobController::class, 'editItem']);
        Route::post('/items/{itemId}/confirm', [OpsJobController::class, 'confirmItem']);
        Route::post('/items/{itemId}/verify', [OpsJobController::class, 'verifyItem']);
        Route::post('/items/{itemID}/save', [OpsJobController::class, 'saveItem']);
        Route::post('/items/{itemID}/add-channel', [OpsJobController::class, 'addChannel']);
        Route::delete('/item-channels/{itemChannelId}', [OpsJobController::class, 'deleteChannel']);
        Route::post('/item-channels/{itemChannelId}/settle-error', [OpsJobController::class, 'settleItemChannelError']);
        Route::post('/items/{itemID}/upload-attachments', [OpsJobController::class, 'uploadItemAttachments']);
        Route::post('/items/{itemID}/cash-collected', [OpsJobController::class, 'itemCashCollected']);
        Route::post('/items/{itemID}/undo-cash-collected', [OpsJobController::class, 'undoItemCashCollected']);
        Route::post('/qty-list/status/{status}', [OpsJobController::class, 'qtyList']);
        Route::post('/{id}/sequence', [OpsJobController::class, 'saveSequence']);

        // Task routes
        Route::post('/{id}/tasks', [OpsJobTaskController::class, 'store']);
        Route::post('/tasks/{taskId}/update', [OpsJobTaskController::class, 'update']);
        Route::post('/tasks/{taskId}/update-sequence', [OpsJobTaskController::class, 'updateSequence']);
        Route::post('/tasks/{taskId}/update-status', [OpsJobTaskController::class, 'updateStatus']);
        Route::post('/tasks/{taskId}/undo-status', [OpsJobTaskController::class, 'undoStatus']);
        Route::delete('/tasks/{taskId}', [OpsJobTaskController::class, 'destroy']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('/sales/{type}/excel', [ReportController::class, 'exportSalesExcel']);
        Route::get('/sales/{type}', [ReportController::class, 'indexSales']);


        Route::get('/gp/vend', [ReportController::class, 'indexGpVm']);
        Route::get('/gp/product', [ReportController::class, 'indexGpProduct']);
        Route::get('/sales-performance/product', [ReportController::class, 'indexSalesPerformanceProduct']);
        Route::get('/gp/category', [ReportController::class, 'indexGpCategory']);
        Route::get('/gp/location-type', [ReportController::class, 'indexGpLocationType']);
        Route::get('/gp/vend/excel', [ReportController::class, 'exportUnitCostVendExcel']);
        Route::get('/gp/product/excel', [ReportController::class, 'exportUnitCostProductExcel']);
        Route::get('/gp/category/excel', [ReportController::class, 'exportUnitCostCategoryExcel']);
        Route::get('/gp/location-type/excel', [ReportController::class, 'exportUnitCostLocationTypeExcel']);

        Route::get('/snapshot', [ReportController::class, 'indexSnapshot']);
        Route::get('/snapshot/excel', [ReportController::class, 'exportSnapshotChannelExcel']);

        Route::get('/stock-count', [ReportController::class, 'indexStockCount']);
        Route::get('/stock-count/excel', [ReportController::class, 'exportStockCountExcel']);
        Route::get('/stock-count-dashboard', [ReportController::class, 'indexStockCountDashboard']);
        Route::get('/machine-health', [ReportController::class, 'indexMachineHealth']);
        Route::get('/machine-health/history', [ReportController::class, 'historyMachineHealth']);
        Route::post('/machine-health/active-alerts', [ReportController::class, 'activeMachineHealthAlerts']);
    });

    Route::prefix('resource-centers')->group(function () {
        Route::get('/', [ResourceCenterController::class, 'index'])->name('resource-centers');
        Route::post('/create', [ResourceCenterController::class, 'create']);
        Route::post('/{id}/update', [ResourceCenterController::class, 'update']);
        Route::delete('/{id}', [ResourceCenterController::class, 'delete']);
    });

    Route::prefix('tutorials')->group(function () {
        Route::get('/', [TutorialController::class, 'index'])->name('tutorials');
    });

    Route::prefix('location-types')->group(function () {
        Route::get('/', [LocationTypeController::class, 'index'])->name('location-types');
        Route::post('/create', [LocationTypeController::class, 'create']);
        Route::post('/{id}/update', [LocationTypeController::class, 'update']);
        Route::delete('/{id}', [LocationTypeController::class, 'delete']);
    });

    Route::prefix('modem-types')->group(function () {
        Route::get('/', [ModemTypeController::class, 'index'])->name('modem-types');
        Route::post('/store', [ModemTypeController::class, 'store']);
        Route::post('/{id}/update', [ModemTypeController::class, 'update']);
        Route::delete('/{id}', [ModemTypeController::class, 'delete']);
    });

    Route::prefix('banks')->group(function () {
        Route::get('/', [BankController::class, 'index'])->name('banks');
        Route::post('/store', [BankController::class, 'store']);
        Route::post('/{id}/update', [BankController::class, 'update']);
        Route::delete('/{id}', [BankController::class, 'delete']);
    });

    Route::prefix('modem-units')->group(function () {
        Route::get('/', [ModemUnitController::class, 'index'])->name('modem-units');
        Route::post('/{id}/reset', [ModemUnitController::class, 'reset']);
        Route::post('/store', [ModemUnitController::class, 'store']);
        Route::post('/{id}/update', [ModemUnitController::class, 'update']);
        Route::delete('/{id}', [ModemUnitController::class, 'delete']);
    });

    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index'])->name('payment-methods');
        Route::post('/create', [PaymentMethodController::class, 'create']);
        Route::post('/{id}/update', [PaymentMethodController::class, 'update']);
        Route::delete('/{id}', [PaymentMethodController::class, 'delete']);
    });

    Route::prefix('products')->group(function () {
        // Product Movement
        Route::get('/movements', [ProductMovementController::class, 'index'])->name('product-movements.index');
        Route::post('/movements', [ProductMovementController::class, 'store'])->name('product-movements.store');
        Route::get('/movements/tracking', [ProductMovementController::class, 'trackingDetails'])->name('product-movements.tracking-details');
        Route::get('/movements/export-excel', [ProductMovementController::class, 'exportExcel'])->name('product-movements.export-excel');
        Route::get('/movements/tracking-export-excel', [ProductMovementController::class, 'trackingExportExcel'])->name('product-movements.tracking-export-excel');
        Route::get('/movements/batch-incoming', [ProductMovementController::class, 'batchIncoming'])->name('product-movements.batch-incoming');
        Route::post('/movements/batch-incoming', [ProductMovementController::class, 'batchStore'])->name('product-movements.batch-store');
        Route::get('/movements/incoming-history', [ProductMovementController::class, 'incomingHistory'])->name('product-movements.incoming-history');
        Route::get('/movements/incoming-history/export', [ProductMovementController::class, 'incomingHistoryExport'])->name('product-movements.incoming-history-export');
        Route::get('/movements/incoming-history/{batch_number}', [ProductMovementController::class, 'incomingBatchDetail'])->name('product-movements.incoming-batch-detail');

        Route::get('/', [ProductController::class, 'index'])->name('products');
        Route::post('/{id}/toggle-activate-deactivate', [ProductController::class, 'toggleActivateDeactivate']);
        Route::post('/{id}/uom-binding', [ProductController::class, 'bindUom']);
        Route::delete('/product-uoms/{productUomId}', [ProductController::class, 'deleteProductUom']);
        Route::get('/create', [ProductController::class, 'create']);
        Route::get('/{id}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::post('/store', [ProductController::class, 'store']);
        Route::post('/{id}/update', [ProductController::class, 'update']);
        Route::post('/{id}/children', [ProductController::class, 'saveChildren']);
        Route::delete('/{id}', [ProductController::class, 'delete']);
        Route::delete('/selling-prices/{sellingPriceId}', [ProductController::class, 'deleteSellingPrice']);
        Route::get('/availability', [ProductController::class, 'availability'])->name('products-availability');
        Route::post('/availability/update-max-ops-job-pick-limit/{product_id}', [ProductController::class, 'updateMaxOpsJobPickLimit'])->name('products-availability.update-max-ops-job-pick-limit');
        Route::post('/availability/toggle-is-available', [ProductController::class, 'toggleIsAvailable'])->name('products-availability.toggle-is-available');
        Route::post('/availability/update-remarks/{product_id}', [ProductController::class, 'updateRemarks'])->name('products-availability.update-remarks');
        Route::get('/availability/export-excel', [ProductController::class, 'exportAvailability'])->name('products-availability.export-excel');



    });

    Route::prefix('product-mappings')->group(function () {
        Route::get('/', [ProductMappingController::class, 'index'])->name('product-mappings');
        Route::post('/create', [ProductMappingController::class, 'create']);
        Route::get('/{id}/edit', [ProductMappingController::class, 'edit'])->name('product-mappings.edit');
        Route::post('/{id}/toggle-activate-deactivate', [ProductMappingController::class, 'toggleActivateDeactivate']);
        Route::post('/{id}/update', [ProductMappingController::class, 'update']);
        Route::post('/{id}/update/vends', [ProductMappingController::class, 'bindVends']);
        Route::post('/{id}/upload-attachments', [ProductMappingController::class, 'uploadAttachment']);
        Route::delete('/{id}', [ProductMappingController::class, 'delete']);
        Route::post('/{id}/items/create', [ProductMappingController::class, 'createItem']);
        Route::post('/items/{itemID}/update', [ProductMappingController::class, 'updateItem']);
        Route::delete('/items/{itemID}', [ProductMappingController::class, 'deleteItem']);
        Route::post('/replicate', [ProductMappingController::class, 'replicate']);
        Route::post('/items/{item}/sequence', [ProductMappingController::class, 'updateItemSequence']);

    });

    Route::prefix('profiles')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('profiles');
        Route::post('/create', [ProfileController::class, 'create']);
        Route::post('/{id}/update', [ProfileController::class, 'update']);
        Route::delete('/{id}', [ProfileController::class, 'delete']);
    });

    Route::prefix('permissions')->group(function () {
        Route::get('/', [RolePermissionController::class, 'indexPermission'])->name('permissions');
        Route::post('/create', [RolePermissionController::class, 'createPermission']);
        Route::post('/{id}/update', [RolePermissionController::class, 'updatePermission']);
        Route::delete('/{id}', [RolePermissionController::class, 'deletePermission']);
    });

    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('settings')
            ->middleware('can:read machine-settings');
        Route::get('/vend/create', [SettingController::class, 'create'])
            ->middleware('can:create machine-settings');
        Route::get('/vend/{id}/update', [SettingController::class, 'edit'])->name('settings.edit')
            ->middleware('can:update machine-settings,read machine-settings');
        Route::get('/vend/{id}/parameter', [SettingController::class, 'parameter'])->name('settings.parameter')
            ->middleware('can:update machine-settings,read machine-settings');
        Route::post('/vend/{id}/parameter', [SettingController::class, 'updateParameter'])
            ->middleware('can:update machine-settings');
        Route::post('/vend/store', [SettingController::class, 'store'])
            ->middleware('can:create machine-settings');
        Route::post('/{id}/toggle-activation', [SettingController::class, 'toggleActivation'])
            ->middleware('can:update machine-settings');
    });

    Route::prefix('machine-alert-parameters')->group(function () {
        Route::get('/', [VendAlertParameterController::class, 'index'])->name('machine-alert-parameters');
        Route::post('/bulk-update', [VendAlertParameterController::class, 'bulkUpdate'])->name('machine-alert-parameters.bulk-update');
    });

    Route::prefix('simcards')->group(function () {
        Route::get('/', [SimcardController::class, 'index'])->name('simcards');
        Route::post('/store', [SimcardController::class, 'store']);
        Route::post('/{id}/update', [SimcardController::class, 'update']);
        Route::delete('/{id}', [SimcardController::class, 'delete']);
    });

    Route::prefix('statuses')->group(function () {
        Route::get('/', [StatusController::class, 'index'])->name('statuses');
        Route::post('/create', [StatusController::class, 'create']);
        Route::post('/{id}/update', [StatusController::class, 'update']);
        Route::delete('/{id}', [StatusController::class, 'delete']);
    });

    Route::prefix('tags')->group(function () {
        Route::get('/', [TagController::class, 'index'])->name('tags');
        Route::post('/create', [TagController::class, 'create']);
        Route::post('/{id}/update', [TagController::class, 'update']);
        Route::delete('/{id}', [TagController::class, 'delete']);
    });

    Route::prefix('taxes')->group(function () {
        Route::get('/', [TaxController::class, 'index'])->name('taxes');
        Route::post('/create', [TaxController::class, 'create']);
        Route::post('/{id}/update', [TaxController::class, 'update']);
        Route::delete('/{id}', [TaxController::class, 'delete']);
    });

    Route::prefix('telcos')->group(function () {
        Route::get('/', [TelcoController::class, 'index'])->name('telcos');
        Route::post('/create', [TelcoController::class, 'create']);
        Route::post('/{id}/update', [TelcoController::class, 'update']);
        Route::delete('/{id}', [TelcoController::class, 'delete']);
    });

    Route::prefix('roles')->group(function () {
        Route::get('/', [RolePermissionController::class, 'indexRole'])->name('roles');
        Route::post('/create', [RolePermissionController::class, 'createRole']);
        Route::post('/{id}/update', [RolePermissionController::class, 'updateRole']);
        Route::delete('/{id}', [RolePermissionController::class, 'deleteRole']);
    });

    Route::prefix('self')->group(function () {
        Route::get('/', [UserController::class, 'selfIndex'])->name('self');
        Route::post('/{id}/update', [UserController::class, 'selfUpdate']);
    });

    Route::prefix('uoms')->group(function () {
        Route::get('/', [UomController::class, 'index'])->name('uoms');
        Route::post('/create', [UomController::class, 'create']);
        Route::post('/{id}/update', [UomController::class, 'update']);
        Route::delete('/{id}', [UomController::class, 'delete']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('users');
        Route::post('/create', [UserController::class, 'create']);
        Route::get('/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::post('/{id}/update', [UserController::class, 'update']);
        Route::post('/{id}/toggle-activate-deactivate', [UserController::class, 'toggleActivateDeactivate']);
        Route::delete('/{id}', [UserController::class, 'delete']);
        Route::post('/bind-vend', [UserController::class, 'bindVend']);
        Route::post('/unbind-vend', [UserController::class, 'unbindVend']);
    });

    Route::prefix('vends')->group(function () {
        Route::post('/create', [VendController::class, 'create']);
        Route::get('/channels/excel', [VendController::class, 'exportChannelExcel']);
        Route::get('/customers', [VendController::class, 'indexCustomer'])->name('vends.customer');
        Route::get('/ops-performance', [OpsPerformanceController::class, 'index'])->name('vends.ops-performance');
        Route::get('/ops-performance/excel', [OpsPerformanceController::class, 'export'])->name('vends.ops-performance.excel');
        Route::get('/', [VendController::class, 'index'])->name('vends');
        Route::get('/{id}/edit', [VendController::class, 'edit'])->name('vends.edit');
        Route::get('/{vend}/logs', [VendController::class, 'logs']);
        Route::get('/{id}/temp/{type}', [VendController::class, 'temp'])->name('temp');
        Route::get('/{id}/temp/{type}/excel', [VendController::class, 'exportTempExcel']);
        Route::get('/transactions', [VendController::class, 'transactionIndex'])->name('vends-transactions');
        Route::get('/transactions/excel', [VendController::class, 'exportTransactionExcel']);
        Route::get('/transactions/export-csv', [VendController::class, 'exportTransactionCsv'])->name('vends.transactions.export-csv');
        Route::delete('/transactions/latest-exports/{id}', [VendController::class, 'deleteLatestExportTransaction']);
        // Route::get('/vends/transactions/latest-exports', [VendController::class, 'latestExports']);
        Route::get('/transactions-daily-summary', [VendController::class, 'dailySummaryIndex'])->name('vends.transactions.daily-summary');
        Route::get('/transactions-daily-summary/export-csv', [VendController::class, 'exportDailySummaryCsv'])->name('vends.transactions.daily-summary.export-csv');
        Route::get('/payment-gateway-transactions', [VendController::class, 'paymentGatewayTransactionIndex'])->name('payment-gateway-transactions');
        Route::get('/payment-gateway-transactions/excel', [VendController::class, 'exportPaymentGatewayTransactionExcel']);
        Route::get('/vend-snapshots/excel/{vendSnapshotId}', [VendController::class, 'exportVendSnapshotExcel']);
        Route::get('/channel-error-logs-email', [VendController::class, 'channelErrorLogsEmail']);
        Route::post('/{id}/channels-error-rate', [VendController::class, 'getChannelsErrorRate']);
        Route::post('/{id}/update', [VendController::class, 'update']);
        Route::post('/{id}/unbind/{returnUrl?}', [VendController::class, 'unbindCustomer']);
        Route::post('/{id}/edit-products', [VendController::class, 'editProducts']);
        Route::post('/{id}/dispense-product', [VendController::class, 'dispenseProduct']);
        Route::post('/{id}/restart-apk', [VendController::class, 'restartAPK']);
        Route::post('/{id}/restart-vmc', [VendController::class, 'restartVMC']);
        Route::post('/{id}/sync-apk-settings', [VendController::class, 'syncApkSettings']);
        Route::post('/{id}/sync-vend-channels', [VendController::class, 'syncVendChannels']);
        Route::post('/{id}/trigger-log-upload', [VendController::class, 'triggerLogUpload']);
        Route::post('/{id}/unbind-customer/{returnUrl?}', [VendController::class, 'unbindCustomer']);
        Route::post('/{id}/unbind-customer-deactivate/{returnUrl?}', [VendController::class, 'unbindCustomerDeactivate']);
        Route::post('/pick-lists', [VendController::class, 'pickLists']);
        Route::post('/{id}/promote-upcoming-product-mapping', [VendController::class, 'promoteUpcomingProductMapping']);
        Route::post('/{id}/replace-product-mapping', [VendController::class, 'replaceProductMapping']);
        Route::post('/{id}/upload-attachments', [VendController::class, 'uploadAttachment']);
    });

    Route::prefix('vend-channel-errors')->group(function () {
        Route::get('/', [VendChannelErrorController::class, 'index'])->name('vend-channel-errors');
        Route::post('/create', [VendChannelErrorController::class, 'create']);
        Route::post('/{id}/update', [VendChannelErrorController::class, 'update']);
        Route::delete('/{id}', [VendChannelErrorController::class, 'delete']);
    });

    Route::prefix('vend-configs')->group(function () {
        Route::get('/', [VendConfigController::class, 'index'])->name('vend-configs');
        Route::post('/create', [VendConfigController::class, 'create']);
        Route::get('/{id}/edit', [VendConfigController::class, 'edit'])->name('vend-configs.edit');
        Route::post('/{id}/update', [VendConfigController::class, 'update']);
        Route::delete('/{id}', [VendConfigController::class, 'delete']);
        Route::post('/{id}/toggle-activate-deactivate', [VendConfigController::class, 'toggleActivateDeactivate']);
        Route::post('/{id}/upload-attachments', [VendConfigController::class, 'uploadAttachment']);
    });

    Route::prefix('vend-contracts')->group(function () {
        Route::get('/', [VendContractController::class, 'index'])->name('vend-contracts');
        Route::post('/store', [VendContractController::class, 'store']);
        Route::post('/{id}/update', [VendContractController::class, 'update']);
        Route::delete('/{id}', [VendContractController::class, 'delete']);
    });

    Route::prefix('vend-models')->group(function () {
        Route::get('/', [VendModelController::class, 'index'])->name('vend-models');
        Route::post('/store', [VendModelController::class, 'store']);
        Route::post('/{id}/update', [VendModelController::class, 'update']);
        Route::delete('/{id}', [VendModelController::class, 'delete']);
    });

    Route::prefix('vend-prefixes')->group(function () {
        Route::get('/', [VendPrefixController::class, 'index'])->name('vend-prefixes');
        Route::post('/create', [VendPrefixController::class, 'create']);
        Route::get('/{id}/edit', [VendPrefixController::class, 'edit'])->name('vend-prefixes.edit');
        Route::post('/{id}/update', [VendPrefixController::class, 'update']);
        Route::delete('/{id}', [VendPrefixController::class, 'delete']);
    });

    Route::prefix('vend-serial-numbers')->group(function () {
        Route::get('/', [VendSerialNumberController::class, 'index'])->name('vend-serial-numbers')
            ->middleware('can:read serial-numbers');
        Route::post('/store', [VendSerialNumberController::class, 'store'])
            ->middleware('can:create serial-numbers');
        Route::post('/{id}/update', [VendSerialNumberController::class, 'update'])
            ->middleware('can:update serial-numbers');
        Route::delete('/{id}', [VendSerialNumberController::class, 'delete'])
            ->middleware('can:delete serial-numbers');
        Route::get('/excel', [VendSerialNumberController::class, 'exportExcel'])
            ->middleware('can:export serial-numbers');
    });

    Route::prefix('vouchers')->group(function () {
        Route::get('/', [VoucherController::class, 'index'])->name('vouchers');
        Route::get('/create/{batchType}', [VoucherController::class, 'create']);
        Route::get('/edit/{id}', [VoucherController::class, 'edit'])->name('vouchers.edit');
        Route::get('/excel/codes', [VoucherController::class, 'exportExcelVoucherCodes']);
        Route::post('/store', [VoucherController::class, 'store']);
        Route::post('/{id}/update', [VoucherController::class, 'update']);
        Route::delete('/{id}', [VoucherController::class, 'delete']);
    });

    Route::prefix('zones')->group(function () {
        Route::get('/', [ZoneController::class, 'index'])->name('zones');
        Route::post('/create', [ZoneController::class, 'create']);
        Route::post('/{id}/update', [ZoneController::class, 'update']);
        Route::delete('/{id}', [ZoneController::class, 'delete']);
    });

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
});

require __DIR__ . '/auth.php';
