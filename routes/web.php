<?php

use App\Http\Controllers\Api\V1\VendDataController;
use App\Http\Controllers\ApkReleaseController;
use App\Http\Controllers\ApkSettingController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CardTerminalController;
use App\Http\Controllers\CashlessTerminalController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryGroupController;
use App\Http\Controllers\CommissionSettlementController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryPlatformCampaignController;
use App\Http\Controllers\DeliveryPlatformOrderController;
use App\Http\Controllers\DeliveryPlatformRefNumberController;
use App\Http\Controllers\DeliveryProductMappingController;
use App\Http\Controllers\DeliveryProductMappingVendController;
use App\Http\Controllers\HidCardController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\KeyController;
use App\Http\Controllers\LocationTypeController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\McpOAuthController;
use App\Http\Controllers\McpTokenController;
use App\Http\Controllers\ModemTypeController;
use App\Http\Controllers\ModemUnitController;
use App\Http\Controllers\OauthController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\OpsJobController;
use App\Http\Controllers\OpsJobTaskController;
use App\Http\Controllers\OpsPerformanceController;
use App\Http\Controllers\OtaController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PayoutGroupController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductMappingController;
use App\Http\Controllers\ProductMovementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\RefundFormController;
use App\Http\Controllers\RefundSettlementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResourceCenterController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SimcardController;
use App\Http\Controllers\SiteGroupingController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TelcoController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TutorialController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserLogController;
use App\Http\Controllers\VendAlertParameterController;
use App\Http\Controllers\VendChannelErrorController;
use App\Http\Controllers\VendConfigController;
use App\Http\Controllers\VendContractController;
use App\Http\Controllers\VendController;
use App\Http\Controllers\VendCriteriaBindingController;
use App\Http\Controllers\VendCriteriaController;
use App\Http\Controllers\VendModelController;
use App\Http\Controllers\VendPrefixController;
use App\Http\Controllers\VendScreenshotController;
use App\Http\Controllers\VendSerialNumberController;
use App\Http\Controllers\VendStickerController;
use App\Http\Controllers\VisitorHistoryController;
use App\Http\Controllers\VoucherController;
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

    return redirect('/login');
    // return Inertia::render('Dashboard', [
    //     'canLogin' => Route::has('login'),
    //     'canRegister' => Route::has('register'),
    //     'laravelVersion' => Application::VERSION,
    //     'phpVersion' => PHP_VERSION,
    // ]);
});

Route::post('/SetPara2', [VendDataController::class, 'create']);

// Device-facing Smart-Freezer APK OTA manifest (unauthenticated for v1, parity with
// the /menu endpoint; on-device sha256 + signer pinning is the real control). GET only.
Route::get('/ota/manifest', [OtaController::class, 'manifest'])->middleware('throttle:120,1');

// OAuth 2.0 discovery metadata for the MCP connector (RFC 8414). Public,
// GET-only, cacheable. See McpOAuthController — additive to Passport.
Route::get('/.well-known/oauth-authorization-server', [McpOAuthController::class, 'authorizationServerMetadata'])
    ->middleware('throttle:60,1');
Route::get('/.well-known/openid-configuration', [McpOAuthController::class, 'authorizationServerMetadata'])
    ->middleware('throttle:60,1');

Route::get('/client/api-docs', function () {
    return Inertia::render('Client/ApiDocs');
});

Route::middleware(['auth', 'cors'])->group(function () {

    // Generic user-action history feed for the reusable HistoryButton drawer.
    Route::get('/user-logs', [UserLogController::class, 'index'])->name('user-logs.index');

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
        // Combined picture+video uploads for the unified media sections; the
        // four per-kind routes above stay for API/backward compatibility.
        Route::post('/{id}/upload-media', [ApkSettingController::class, 'uploadMedia']);
        Route::post('/{id}/upload-campaign-media', [ApkSettingController::class, 'uploadCampaignMedia']);
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

    // 2026-08-09: this group had NO authorisation at all - CampaignController does not
    // authorize() and the group sits in the bare ['auth','cors'] middleware, so the
    // permission was nav-cosmetic and /campaigns was reachable by typing the URL.
    Route::prefix('campaigns')->group(function () {
        Route::get('/', [CampaignController::class, 'index'])->name('campaigns')
            ->middleware('can:read campaigns');
        Route::get('/create', [CampaignController::class, 'createView'])->name('campaigns.create')
            ->middleware('can:create campaigns');
        Route::post('/create', [CampaignController::class, 'create'])
            ->middleware('can:create campaigns');
        Route::get('/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit')
            ->middleware('can:update campaigns');
        Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy')
            ->middleware('can:delete campaigns');
        Route::post('/{campaign}/update', [CampaignController::class, 'update'])
            ->middleware('can:update campaigns');
        Route::delete('/{id}', [CampaignController::class, 'delete'])
            ->middleware('can:delete campaigns');
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
        // "Access Product(s)": Site Summary money is whole-site and its
        // location-fee rates are tiered on TOTAL site sales, so there is no
        // well-defined per-product share. Blocked for restricted viewers rather
        // than shown unfiltered. See BlockWhenProductRestricted.
        Route::get('/summary', [CustomerController::class, 'summary'])
            ->middleware('product.unrestricted')
            ->name('customers.summary');
        Route::get('/summary/excel', [CustomerController::class, 'summaryExportExcel'])
            ->middleware('product.unrestricted')
            ->name('customers.summary.excel');
        // Site Performance — aggregate matrix report (metrics × period columns).
        // Placed before the {id} routes below so the literal "performance"
        // segment is never swallowed by a wildcard. Read-only; mirrors the
        // Sites / Summary & Comm filters.
        // "Access Product(s)": buildPerformanceMatrix() reads
        // customer_period_summaries — whole-SITE money with no product
        // dimension, exactly like /customers/summary above. Blocked for
        // restricted viewers rather than shown unfiltered.
        Route::get('/performance', [CustomerController::class, 'performance'])
            ->middleware('product.unrestricted')
            ->name('customers.performance');
        Route::get('/performance/excel', [CustomerController::class, 'performanceExportExcel'])
            ->middleware('product.unrestricted')
            ->name('customers.performance.excel');
        // "Pull from CMS" (Create/Edit ▸ beside CMS Linking ID) — on-demand,
        // one-way CMS → form fetch (read-only JSON; nothing is saved until the
        // user presses Save, and nothing is pushed back to CMS). Literal path,
        // kept above the {id} wildcard routes below.
        Route::get('/cms-person-pull', [CustomerController::class, 'pullCmsPerson'])
            ->name('customers.cms-person-pull');
        // Action-triggered lock / unlock for a single Customer Summary row
        // (by customer_period_summaries.id). Lock = admin-access customers;
        // unlock is gated to superadmin/admin/supervisor in the controller.
        Route::post('/summary/{id}/lock', [CustomerController::class, 'lockCustomerPeriodSummary'])
            ->name('customers.summary.lock');
        Route::post('/summary/{id}/unlock', [CustomerController::class, 'unlockCustomerPeriodSummary'])
            ->name('customers.summary.unlock');
        // Paid / Unpaid for a locked Customer Summary row. Paid = same
        // permission as Lock (admin-access customers); Unpaid = same as
        // Unlock (superadmin / admin / supervisor) since it reverses a recorded action.
        // Unlock is server-blocked when paid_at IS NOT NULL — the user has
        // to Unpaid first (the UI also disables the Unlock button).
        Route::post('/summary/{id}/paid', [CustomerController::class, 'markPaidCustomerPeriodSummary'])
            ->name('customers.summary.paid');
        Route::post('/summary/{id}/unpaid', [CustomerController::class, 'markUnpaidCustomerPeriodSummary'])
            ->name('customers.summary.unpaid');
        // Batch bar — Lock / Mark Paid for MANY summary rows in one request
        // (row checkboxes + select-all on the Summary page). Same permission
        // tiers as the single-row endpoints; per-row eligibility is
        // re-checked server-side and ineligible ids are skipped.
        Route::post('/summary/batch-lock', [CustomerController::class, 'batchLockCustomerPeriodSummaries'])
            ->name('customers.summary.batch-lock');
        Route::post('/summary/batch-paid', [CustomerController::class, 'batchMarkPaidCustomerPeriodSummaries'])
            ->name('customers.summary.batch-paid');
        // Batch CIMB commission export — locked+unpaid ticked rows → CIMB
        // BizChannel bulk payment txt (one line per site+month, Net Loc Fee).
        // Download only, no state change; eligibility re-checked server-side.
        Route::post('/summary/export-cimb', [CustomerController::class, 'exportCommissionBankFile'])
            ->name('customers.summary.export-cimb');
        // Batch Report Content — ticked rows on the Summary page → structured
        // Report Content per (customer, period) in one round trip, stitched
        // client-side into a single email body ("Export Batch Report Content").
        // Read-only (no writes); mirrors the single-row performance-report-content.
        Route::post('/summary/batch-report-content', [CustomerController::class, 'batchPerformanceReportContent'])
            ->name('customers.summary.batch-report-content');
        // Batch "Paid Report" — payment-advice emails for paid (settlement) rows.
        Route::post('/summary/batch-paid-report', [CustomerController::class, 'batchPaidReport'])
            ->name('customers.summary.batch-paid-report');
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
        // Future-dated placement contract change (applied by contract:apply-scheduled).
        Route::post('/{id}/scheduled-contract', [CustomerController::class, 'storeScheduledContract'])
            ->name('customers.scheduled-contract.store');
        Route::delete('/{id}/scheduled-contract', [CustomerController::class, 'cancelScheduledContract'])
            ->name('customers.scheduled-contract.cancel');
        Route::post('/{id}/toggle-activation', [CustomerController::class, 'toggleActivation']);
        Route::delete('/{id}', [CustomerController::class, 'delete']);
        Route::get('/excel', [CustomerController::class, 'exportExcel']);
        Route::post('/{id}/upload-attachments', [CustomerController::class, 'uploadAttachment']);
        Route::post('/{id}/upload-photos', [CustomerController::class, 'uploadPhoto']);
        Route::post('/{id}/upload-contracts', [CustomerController::class, 'uploadContract']);
        Route::post('/{id}/bind-vend', [CustomerController::class, 'bindVend']);
        Route::get('/{id}/selling-prices/type/{type}', [CustomerController::class, 'getProductSellingPrices']);
        Route::post('/{id}/disconnect-cms', [CustomerController::class, 'disconnectCms']);
        // Customer-level notes — edited inline on the Customer Summary page
        // (Customer Tag column). Mirrors products-availability.update-remarks.
        Route::post('/{id}/update-notes', [CustomerController::class, 'updateNotes'])->name('customers.update-notes');
        // Machine binding history for a SITE — drives the clock-icon popup on the
        // Site Summary (which machines were bound to this site, with timestamps).
        Route::get('/{id}/vend-bindings', [CustomerController::class, 'vendBindings'])->name('customers.vend-bindings');
        // Customer-level "Remarks for Loc Fees" — edited inline on the
        // Customer Summary page (rightmost column). Same shape as
        // update-notes; standalone field, no unread tracking.
        Route::post('/{id}/update-loc-fee-remarks', [CustomerController::class, 'updateLocFeeRemarks'])->name('customers.update-loc-fee-remarks');
        // Settlement ledger ("Payment History") for a SITE — drives the
        // Payment-History popup on the Site Summary. Read-only JSON: the full
        // chronological ledger + derived running balance + outstanding total.
        Route::get('/{id}/settlements', [CustomerController::class, 'getSettlements'])
            ->middleware('product.unrestricted')
            ->name('customers.settlements');
        // Settlement ledger exports — Excel (.xlsx) + printable Statement of
        // Account (HTML → browser Save-as-PDF). Both reuse buildSettlementLedger.
        Route::get('/{id}/settlements/excel', [CustomerController::class, 'settlementsExportExcel'])
            ->middleware('product.unrestricted')
            ->name('customers.settlements.excel');
        Route::get('/{id}/settlements/pdf', [CustomerController::class, 'settlementsPrintView'])
            ->middleware('product.unrestricted')
            ->name('customers.settlements.pdf');
        // Add a manual Paid/Waived credit entry straight from the Payment-History
        // popup (admin-only). Standalone ledger credit — does NOT touch any
        // period's Paid/Waived flags.
        Route::post('/{id}/settlements', [CustomerController::class, 'storeSettlement'])->name('customers.settlements.store');
        // Edit a settlement ledger entry's amount/remarks (admin-only; only
        // manually-owned entry types: opening_balance + adjustment + manual
        // payment/waiver added from the popup).
        Route::post('/settlements/{id}/update', [CustomerController::class, 'updateSettlement'])->name('customers.settlements.update');
        // Delete a manually-added settlement entry (admin-only; source=manual).
        Route::post('/settlements/{id}/delete', [CustomerController::class, 'deleteSettlement'])->name('customers.settlements.delete');
        // Ops-side free-text note (refilling/operations) — edited inline on
        // Vend/CustomerIndex "Refilling Routes" column. Same shape as
        // update-notes; lives under the same /customers prefix because it
        // writes a column on the customer record.
        Route::post('/{id}/update-ops-note', [CustomerController::class, 'updateOpsNote'])->name('customers.update-ops-note');
        Route::post('/map', [CustomerController::class, 'getMap']);
    });

    Route::prefix('dashboard')->group(function () {
        Route::redirect('/', '/dashboard/performance');
        // "Access Product(s)": deliberately NOT product.unrestricted, unlike the
        // other vend_records-backed surfaces. This page reads frozen vend_records
        // (grain date x machine, no product dimension) so its figures cover EVERY
        // product in a machine - but the decision (2026-08-05) is to keep showing
        // them to product-restricted viewers until vend_records gains a product
        // dimension. The page carries a banner saying so; see Dashboard.vue.
        Route::get('/performance', [DashboardController::class, 'index'])
            ->name('dashboard');
        // Dashboard > Performance (Lite) - the SAME page, the SAME charts, read
        // from vend_product_records instead of vend_records.
        //
        // Its own permission, deliberately NOT `read dashboard-performance`:
        // "Lite but not Full" is the whole point. prod_owner holds the Lite one
        // and not the Full one, so the product owner gets figures narrowed to
        // their own SKUs (VendProductRecord carries
        // ProductAccessProductColumnScope) and cannot reach the whole-machine
        // page. Both routes are gated in DashboardController's constructor -
        // hiding the sidebar link is not a control on its own.
        Route::get('/performance-lite', [DashboardController::class, 'indexLite'])
            ->name('dashboard.performance-lite');
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
        // Smart Chiller (CityBox) driver actions on an item — access rule inside the controller (§6b.3).
        Route::post('/items/{id}/citybox-open-door', [\App\Http\Controllers\Citybox\CityboxOpsJobItemController::class, 'openDoor'])->name('ops-job-items.citybox-open-door');
        Route::post('/items/{id}/citybox-retry-submit', [\App\Http\Controllers\Citybox\CityboxOpsJobItemController::class, 'retrySubmit'])->name('ops-job-items.citybox-retry-submit');
        Route::get('/items/{id}/citybox-door-opens', [\App\Http\Controllers\Citybox\CityboxOpsJobItemController::class, 'doorOpens'])->name('ops-job-items.citybox-door-opens');
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
        // "Access Product(s)": the historical leg of this report reads vend_records
        // directly (grain = date x machine, no product dimension), so it cannot be
        // honestly filtered - see BlockWhenProductRestricted. The excel export runs
        // the SAME query (getSalesQuery), so it carries the same guard - without it
        // a restricted viewer could pull whole-machine revenue via the direct URL.
        Route::get('/sales/{type}/excel', [ReportController::class, 'exportSalesExcel'])
            ->middleware('product.unrestricted');
        Route::get('/sales/{type}', [ReportController::class, 'indexSales'])
            ->middleware('product.unrestricted');

        // 2026-08-09: margin reports moved off 'reports' onto 'reports-gp'
        // (superadmin/admin/supervisor). These had no gate, so hiding the nav links
        // alone would have left them reachable by URL.
        Route::get('/gp/vend', [ReportController::class, 'indexGpVm'])
            ->middleware('can:read reports-gp');
        Route::get('/gp/product', [ReportController::class, 'indexGpProduct'])
            ->middleware('can:read reports-gp');
        Route::get('/sales-performance/product', [ReportController::class, 'indexSalesPerformanceProduct'])
            ->middleware('can:read reports-gp');
        Route::get('/gp/category', [ReportController::class, 'indexGpCategory'])
            ->middleware('can:read reports-gp');
        Route::get('/gp/location-type', [ReportController::class, 'indexGpLocationType'])
            ->middleware('can:read reports-gp');
        Route::get('/gp/vend/excel', [ReportController::class, 'exportUnitCostVendExcel'])
            ->middleware('can:export reports-gp');
        Route::get('/gp/product/excel', [ReportController::class, 'exportUnitCostProductExcel'])
            ->middleware('can:export reports-gp');
        Route::get('/gp/category/excel', [ReportController::class, 'exportUnitCostCategoryExcel'])
            ->middleware('can:export reports-gp');
        Route::get('/gp/location-type/excel', [ReportController::class, 'exportUnitCostLocationTypeExcel'])
            ->middleware('can:export reports-gp');

        Route::get('/snapshot', [ReportController::class, 'indexSnapshot'])
            ->middleware('can:read reports-gp');
        Route::get('/snapshot/excel', [ReportController::class, 'exportSnapshotChannelExcel'])
            ->middleware('can:export reports-gp');

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

    // Operator Groups (payout groups) — admin only. Manage the shared CIMB account
    // and which operators belong to each group (drives refund settlement grouping).
    Route::prefix('operator-groups')->group(function () {
        Route::get('/', [PayoutGroupController::class, 'index'])->name('operator-groups')->middleware('can:read operator-groups');
        Route::post('/store', [PayoutGroupController::class, 'store'])->middleware('can:manage operator-groups');
        Route::post('/{id}/update', [PayoutGroupController::class, 'update'])->middleware('can:manage operator-groups');
        Route::delete('/{id}', [PayoutGroupController::class, 'delete'])->middleware('can:manage operator-groups');
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

    // CityBox Products — mapping screen for the smart-chiller catalog mirror (§5.5).
    // Permission gates live in the controller constructor (read/update products).
    Route::prefix('citybox')->group(function () {
        // Vend provisioning from the CityBox fleet (Create page radio branch, §8c).
        Route::get('/devices', [\App\Http\Controllers\Citybox\CityboxProvisioningController::class, 'devices'])->name('citybox.devices');
        Route::get('/devices/{equipmentId}/preview', [\App\Http\Controllers\Citybox\CityboxProvisioningController::class, 'preview'])->name('citybox.devices.preview');
        Route::post('/vends', [\App\Http\Controllers\Citybox\CityboxProvisioningController::class, 'store'])->name('citybox.vends.store');
        Route::get('/customers/search', [\App\Http\Controllers\Citybox\CityboxProvisioningController::class, 'customerSearch'])->name('citybox.customers.search');
        Route::get('/products', [\App\Http\Controllers\Citybox\CityboxProductController::class, 'index'])->name('citybox.products');
        Route::post('/products/sync', [\App\Http\Controllers\Citybox\CityboxProductController::class, 'syncNow'])->name('citybox.products.sync');
        Route::post('/products/{id}/map', [\App\Http\Controllers\Citybox\CityboxProductController::class, 'map'])->name('citybox.products.map');
        Route::get('/products/search', [\App\Http\Controllers\Citybox\CityboxProductController::class, 'productSearch'])->name('citybox.products.search');
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
        // Machines behind the "N Machine(s) at upcoming stage" figure on the
        // index — read-only JSON, fetched when that line is clicked.
        Route::get('/{id}/upcoming-vends', [ProductMappingController::class, 'upcomingVends'])->name('product-mappings.upcoming-vends');
        Route::post('/{id}/toggle-activate-deactivate', [ProductMappingController::class, 'toggleActivateDeactivate']);
        Route::post('/{id}/toggle-smart', [ProductMappingController::class, 'toggleSmart']);
        Route::post('/{id}/update', [ProductMappingController::class, 'update']);
        Route::post('/{id}/update/vends', [ProductMappingController::class, 'bindVends']);
        Route::post('/{id}/upload-attachments', [ProductMappingController::class, 'uploadAttachment']);
        Route::delete('/{id}', [ProductMappingController::class, 'delete']);
        Route::post('/{id}/items/create', [ProductMappingController::class, 'createItem']);
        Route::post('/{id}/baskets/reorder', [ProductMappingController::class, 'reorderBasket']);
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

    // APK OTA Updates — one page, one tab per channel (config/ota.php). Uploading a
    // build and publishing it to the fleet has a far wider blast radius than editing
    // a machine setting, so it carries its own permission set rather than riding
    // machine-settings.
    Route::prefix('apk-releases')->group(function () {
        Route::get('/', [ApkReleaseController::class, 'index'])->name('apk-releases')
            ->middleware('can:read apk-releases');
        Route::post('/releases', [ApkReleaseController::class, 'storeRelease'])
            ->middleware('can:create apk-releases');
        Route::post('/releases/{id}/publish', [ApkReleaseController::class, 'publish'])
            ->middleware('can:update apk-releases');
        Route::post('/releases/{id}/unpublish', [ApkReleaseController::class, 'unpublish'])
            ->middleware('can:update apk-releases');
        Route::post('/releases/{id}/rollout', [ApkReleaseController::class, 'updateRollout'])
            ->middleware('can:update apk-releases');
        Route::post('/releases/{id}/mandatory', [ApkReleaseController::class, 'toggleMandatory'])
            ->middleware('can:update apk-releases');
        Route::delete('/releases/{id}', [ApkReleaseController::class, 'destroy'])
            ->middleware('can:delete apk-releases');
        Route::post('/push-ota-check', [ApkReleaseController::class, 'pushOtaCheck'])
            ->middleware('can:update apk-releases');
    });

    // Former name of the page above; kept so existing bookmarks do not 404.
    Route::redirect('/smart-freezer-settings', '/apk-releases?channel=smart_freezer', 301);

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

    Route::prefix('mcp-tokens')->group(function () {
        Route::get('/', [McpTokenController::class, 'index'])->name('mcp-tokens')
            ->middleware('can:read mcp-tokens');
        Route::post('/create', [McpTokenController::class, 'store'])
            ->middleware('can:manage mcp-tokens');
        Route::delete('/{id}', [McpTokenController::class, 'revoke'])
            ->middleware('can:manage mcp-tokens');
    });

    Route::prefix('vends')->group(function () {
        Route::post('/create', [VendController::class, 'create']);
        Route::get('/channels/excel', [VendController::class, 'exportChannelExcel']);
        // "Access Product(s)": this page prints today/yesterday/7d/30d amounts,
        // "Avg Mthly Sales $" (vend_records_amount_latest) and Stock Cost /
        // Value — every one of them summed across EVERY product in the machine,
        // straight out of customers.vend_transaction_totals_json. There is no
        // per-product share to show, so a restricted viewer is blocked rather
        // than handed the other party's machine revenue. Product restriction is
        // per-USER / per-OPERATOR, NOT per-role, so the `read vend-customers`
        // permission does not stand in for this gate.
        Route::get('/customers', [VendController::class, 'indexCustomer'])
            ->middleware('product.unrestricted')
            ->name('vends.customer');
        // Phase 2 of the Customer Index deferred-load: returns the heavy
        // aggregate columns for the on-screen rows (see indexCustomer
        // $deferAggregates). Same group/middleware as the page above.
        // NOT given product.unrestricted, unlike the page above: Operations >
        // Dashboard (Lite) is open to restricted viewers and uses this same
        // endpoint for its Phase 2, so blocking it made every Lite search 403
        // and silently fall back to a second full-page query. The money it
        // returns is blanked inside customerIndexAggregates() by the same
        // stripWholeMachineMoney() the page itself uses.
        Route::post('/customers/aggregates', [VendController::class, 'customerIndexAggregates'])
            ->name('vends.customer.aggregates');

        // Remote screen capture — Setting > Edit > "View Screen". ONE frame per
        // deliberate click; nothing is stored (see VendScreenshotController).
        // Permission is enforced in the controller, and re-checked on every
        // image() fetch, because a machine screen can show a customer's QR
        // payment or member details.
        Route::post('/{vend}/screenshot', [VendScreenshotController::class, 'request'])
            ->name('vends.screenshot.request')
            ->middleware('can:update machine-settings');
        Route::get('/{vend}/screenshot', [VendScreenshotController::class, 'latest'])
            ->name('vends.screenshot.latest')
            ->middleware('can:update machine-settings');
        // Gated on every fetch, not just once - this is why the image is served
        // here instead of from a public Spaces URL.
        Route::get('/{vend}/screenshot/image', [VendScreenshotController::class, 'image'])
            ->name('vends.screenshot.image')
            ->middleware('can:update machine-settings');
        // Operations > Dashboard (Lite) — glance view of the same Operation
        // Dashboard rows for retail operators: identity, temperatures,
        // inventory status and rolling Sales(qty) only. Shares
        // VendController::indexCustomer (and the /customers/aggregates
        // Phase-2 endpoint above) so filtering, operator scoping and
        // permissions can never drift from the full Dashboard.
        // Shares indexCustomer with the full Dashboard above, but is NOT given
        // the product.unrestricted guard: Lite is the
        // landing page for prod_owner, who is exactly the product-restricted
        // persona. Its vend_channels sub-selects (Stock Value / Full Load /
        // Refillable) are product-filtered in indexCustomer, and the
        // vend_records-derived Sales(qty) figures are blanked there for
        // restricted viewers - so the page is safe without the blanket block.
        Route::get('/customers-lite', [VendController::class, 'indexCustomerLite'])
            ->name('vends.customer-lite');
        // "Access Product(s)": reads ops_machine_daily_snapshots — per-machine
        // sales / stock-in value aggregated over every product.
        // 2026-08-10: gated to match the nav (Authenticated.vue moved both links to
        // 'admin-access vends' per the sheet — superadmin/admin/supervisor). Hiding the
        // link alone would leave the page reachable by URL for every 'read vends' role,
        // the same trap the campaigns/vouchers/gp-reports groups closed on 08-09.
        Route::get('/ops-performance', [OpsPerformanceController::class, 'index'])
            ->middleware(['product.unrestricted', 'can:admin-access vends'])
            ->name('vends.ops-performance');
        Route::get('/ops-performance/excel', [OpsPerformanceController::class, 'export'])
            ->middleware(['product.unrestricted', 'can:admin-access vends'])
            ->name('vends.ops-performance.excel');

        // Operations > Site Grouping — manage co-located Site clusters as objects
        // (create/rename/delete groups, attach/detach member Sites). Membership
        // still lives on customers.customer_group_id, so the "Grouped?" toggle
        // and Customer::siblings() are unaffected. See SiteGroupingController.
        // 2026-08-10: this block had NO authorisation at all — the vends prefix group
        // carries no gate and SiteGroupingController does not authorize(), so the
        // mutating routes (store/update/destroy/members) were open to any signed-in
        // user. Gated to 'admin-access vends' to match the nav and the sheet.
        Route::middleware('can:admin-access vends')->group(function () {
            Route::get('/grouping', [SiteGroupingController::class, 'index'])->name('vends.grouping');
            Route::get('/grouping/site-search', [SiteGroupingController::class, 'searchSites'])->name('vends.grouping.site-search');
            Route::post('/grouping', [SiteGroupingController::class, 'store'])->name('vends.grouping.store');
            Route::put('/grouping/{group}', [SiteGroupingController::class, 'update'])->name('vends.grouping.update');
            Route::delete('/grouping/{group}', [SiteGroupingController::class, 'destroy'])->name('vends.grouping.destroy');
            Route::post('/grouping/{group}/members', [SiteGroupingController::class, 'addMembers'])->name('vends.grouping.members.add');
            Route::delete('/grouping/{group}/members/{customer}', [SiteGroupingController::class, 'removeMember'])->name('vends.grouping.members.remove');
        });
        Route::get('/', [VendController::class, 'index'])->name('vends');
        Route::get('/{id}/edit', [VendController::class, 'edit'])->name('vends.edit');
        Route::get('/{vend}/logs', [VendController::class, 'logs']);
        Route::get('/{vend}/field-audit', [VendController::class, 'fieldAudit'])->name('vends.field-audit')
            ->middleware('can:read machine-settings');
        Route::get('/{vend}/coin-float-history', [VendController::class, 'coinFloatHistory']);
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
        Route::get('/{id}/smart-planogram', [VendController::class, 'smartPlanogram']);
        Route::post('/{id}/dispense-product', [VendController::class, 'dispenseProduct']);
        // Smart Chiller (CityBox) only — ops door-open via zyy_ls_open_door.
        Route::post('/{id}/citybox-open-door', [\App\Http\Controllers\Citybox\CityboxVendActionController::class, 'openDoor']);
        Route::post('/{id}/citybox-pull', [\App\Http\Controllers\Citybox\CityboxVendActionController::class, 'pull']);
        Route::get('/{id}/citybox-planogram', [\App\Http\Controllers\Citybox\CityboxVendActionController::class, 'planogram']);
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

    Route::prefix('machine-stickers')->group(function () {
        Route::get('/', [VendStickerController::class, 'index'])->name('machine-stickers')
            ->middleware('can:read machine-stickers');
        Route::post('/create', [VendStickerController::class, 'create'])
            ->middleware('can:create machine-stickers');
        Route::post('/{id}/update', [VendStickerController::class, 'update'])
            ->middleware('can:update machine-stickers');
        Route::delete('/{id}', [VendStickerController::class, 'delete'])
            ->middleware('can:delete machine-stickers');
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

    // 2026-08-09: as with /campaigns above, this group had no authorisation - the
    // 'read vouchers' permission only ever hid the sidebar link.
    Route::prefix('vouchers')->group(function () {
        Route::get('/', [VoucherController::class, 'index'])->name('vouchers')
            ->middleware('can:read vouchers');
        Route::get('/create/{batchType}', [VoucherController::class, 'create'])
            ->middleware('can:create vouchers');
        Route::get('/edit/{id}', [VoucherController::class, 'edit'])->name('vouchers.edit')
            ->middleware('can:update vouchers');
        Route::get('/excel/codes', [VoucherController::class, 'exportExcelVoucherCodes'])
            ->middleware('can:export vouchers');
        Route::post('/store', [VoucherController::class, 'store'])
            ->middleware('can:create vouchers');
        Route::post('/{id}/update', [VoucherController::class, 'update'])
            ->middleware('can:update vouchers');
        Route::delete('/{id}', [VoucherController::class, 'delete'])
            ->middleware('can:delete vouchers');
    });

    Route::prefix('zones')->group(function () {
        Route::get('/', [ZoneController::class, 'index'])->name('zones');
        Route::post('/create', [ZoneController::class, 'create']);
        Route::post('/{id}/update', [ZoneController::class, 'update']);
        Route::delete('/{id}', [ZoneController::class, 'delete']);
    });

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
});

/*
| Public customer refund form (no auth). Reached from the machine QR:
| /refund?machineID=<vend_code>. JSON endpoints are rate-limited.
*/
Route::get('/refund', [RefundFormController::class, 'show'])->name('refund.form');
Route::post('/refund/resolve', [RefundFormController::class, 'resolve'])->middleware('throttle:60,1')->name('refund.resolve');
Route::post('/refund/candidates', [RefundFormController::class, 'candidates'])->middleware('throttle:60,1')->name('refund.candidates');
Route::post('/refund/machine-products', [RefundFormController::class, 'machineProducts'])->middleware('throttle:60,1')->name('refund.machine-products');
Route::post('/refund', [RefundFormController::class, 'store'])->middleware('throttle:20,1')->name('refund.store');

/*
| Admin refund management (auth + Spatie permissions).
*/
Route::middleware(['auth', 'cors'])->prefix('refunds')->group(function () {
    Route::get('/', [RefundController::class, 'index'])->name('refunds.index')->middleware('can:read refunds');
    // Excel export of the current filtered list. MUST be before the /{ticket}
    // wildcard so 'export' isn't captured as a {ticket} binding.
    Route::get('/export', [RefundController::class, 'export'])->name('refunds.export')->middleware('can:read refunds');
    // NOTE: /batch/complete must be registered BEFORE the /{ticket}/complete
    // wildcard below, or 'batch' would be captured as a {ticket} binding.
    Route::post('/batch/complete', [RefundController::class, 'completeBatch'])->name('refunds.batch.complete')->middleware('can:update refunds');
    // PayPal-only: park an Approved PayPal refund whose payout details are wrong
    // (e.g. bad email). PayPal never enters a settlement, so this is where it's
    // flagged. Must stay before the /{ticket} wildcard below.
    Route::post('/batch/insufficient-info', [RefundController::class, 'insufficientInfoBatch'])->name('refunds.batch.insufficient-info')->middleware('can:update refunds');
    Route::get('/{ticket}', [RefundController::class, 'show'])->name('refunds.show')->middleware('can:read refunds');
    // Local-only: render a workflow email's HTML in the browser to preview the
    // design (?template=received|approved|completed|…). 404s outside local env.
    Route::get('/{ticket}/email-preview', [RefundController::class, 'emailPreview'])->middleware('can:read refunds');
    Route::post('/{ticket}/match', [RefundController::class, 'match'])->middleware('can:update refunds');
    Route::post('/{ticket}/clear-match', [RefundController::class, 'clearMatch'])->middleware('can:update refunds');
    Route::post('/{ticket}/verify', [RefundController::class, 'verify'])->middleware('can:verify refunds');
    Route::post('/{ticket}/reject', [RefundController::class, 'reject'])->middleware('can:verify refunds');
    Route::post('/{ticket}/pending', [RefundController::class, 'markPending'])->middleware('can:verify refunds');
    Route::post('/{ticket}/resolve-no-charge', [RefundController::class, 'resolveNoCharge'])->middleware('can:verify refunds');
    Route::post('/{ticket}/drop', [RefundController::class, 'drop'])->middleware('can:verify refunds');
    Route::post('/{ticket}/undrop', [RefundController::class, 'undrop'])->middleware('can:verify refunds');
    Route::post('/{ticket}/request-info', [RefundController::class, 'requestInfo'])->middleware('can:update refunds');
    // Overwritten-section controls: also reachable by the supervisor role, even
    // without the update/verify refund permissions (the rest of the workflow stays
    // permission-only). superadmin still bypasses via Gate::before.
    Route::post('/{ticket}/final-amount', [RefundController::class, 'updateFinalAmount'])->middleware('role_or_permission:supervisor|update refunds');
    Route::post('/{ticket}/override-status', [RefundController::class, 'overrideStatus'])->middleware('role_or_permission:supervisor|verify refunds');
    Route::post('/{ticket}/complete', [RefundController::class, 'complete'])->middleware('can:update refunds');
    Route::post('/{ticket}/email', [RefundController::class, 'sendEmail'])->middleware('can:update refunds');
    Route::post('/{ticket}/items/{item}', [RefundController::class, 'updateItem'])->middleware('can:update refunds');
    Route::get('/{ticket}/attachments/{attachment}', [RefundController::class, 'viewAttachment'])->middleware('can:read refunds');
    Route::delete('/{ticket}', [RefundController::class, 'destroy'])->middleware('can:update refunds');
    Route::post('/batch/generate', [RefundController::class, 'generateBatch'])->name('refunds.batch.generate')->middleware('can:payout refunds');
    Route::post('/batch/export', [RefundController::class, 'exportBatch'])->name('refunds.batch.export')->middleware('can:payout refunds');
    Route::get('/batch/{batch}/download', [RefundController::class, 'downloadBatch'])->name('refunds.batch.download')->middleware('can:payout refunds');
    Route::post('/batch/{batch}/uploaded', [RefundController::class, 'markBatchUploaded'])->middleware('can:payout refunds');
});

/*
| Refund Settlement — batch the approved refunds, export CIMB (.txt) / PayPal
| (.xlsx) from the settlement, then mark the paid rows done.
*/
Route::middleware(['auth', 'cors'])->prefix('refund-settlements')->group(function () {
    Route::get('/', [RefundSettlementController::class, 'index'])->name('refund-settlements.index')->middleware('can:read refunds');
    // Static routes BEFORE the /{settlement} wildcard so they aren't captured as a binding.
    Route::post('/push', [RefundSettlementController::class, 'push'])->name('refund-settlements.push')->middleware('can:payout refunds');
    Route::get('/{settlement}', [RefundSettlementController::class, 'show'])->name('refund-settlements.show')->middleware('can:read refunds');
    Route::post('/{settlement}/close', [RefundSettlementController::class, 'close'])->middleware('can:payout refunds');
    Route::post('/{settlement}/reopen', [RefundSettlementController::class, 'reopen'])->middleware('can:payout refunds');
    Route::post('/{settlement}/export-cimb', [RefundSettlementController::class, 'exportCimb'])->middleware('can:payout refunds');
    Route::post('/{settlement}/export-xlsx', [RefundSettlementController::class, 'exportXlsx'])->middleware('can:payout refunds');
    Route::get('/{settlement}/exports/{export}/download', [RefundSettlementController::class, 'downloadExport'])->middleware('can:payout refunds');
    Route::post('/{settlement}/mark-done', [RefundSettlementController::class, 'markDone'])->middleware('can:payout refunds');
    Route::post('/{settlement}/mark-insufficient-info', [RefundSettlementController::class, 'markInsufficientInfo'])->middleware('can:payout refunds');
    Route::post('/{settlement}/return-to-pool/{ticket}', [RefundSettlementController::class, 'returnToPool'])->middleware('can:payout refunds');
    Route::delete('/{settlement}', [RefundSettlementController::class, 'destroy'])->middleware('can:payout refunds');
});

/*
| Site Settlement — batch site location-fee / commission payouts, export CIMB,
| mark rows paid (posts the ledger credit). Admin only.
*/
Route::middleware(['auth', 'cors'])->prefix('site-settlements')->group(function () {
    Route::get('/', [CommissionSettlementController::class, 'index'])->name('site-settlements.index')->middleware('can:admin-access customers');
    Route::post('/push', [CommissionSettlementController::class, 'push'])->name('site-settlements.push')->middleware('can:admin-access customers');
    Route::get('/{settlement}', [CommissionSettlementController::class, 'show'])->name('site-settlements.show')->middleware('can:admin-access customers');
    Route::post('/{settlement}/reopen', [CommissionSettlementController::class, 'reopen'])->middleware('can:admin-access customers');
    Route::post('/{settlement}/export-cimb', [CommissionSettlementController::class, 'exportCimb'])->middleware('can:admin-access customers');
    Route::get('/{settlement}/exports/{export}/download', [CommissionSettlementController::class, 'downloadExport'])->middleware('can:admin-access customers');
    Route::post('/{settlement}/mark-done', [CommissionSettlementController::class, 'markDone'])->middleware('can:admin-access customers');
    Route::post('/{settlement}/return-to-pool/{summary}', [CommissionSettlementController::class, 'returnToPool'])->middleware('can:admin-access customers');
    Route::delete('/{settlement}', [CommissionSettlementController::class, 'destroy'])->middleware('can:admin-access customers');
});

/*
| Admin > Visitor History — login sessions, IP/device/browser, and the pages
| each user opened. Read-only; rows are written by LogVisitorActivity and the
| auth listeners. `ping` is the browser dwell-time beacon, so it is auth-gated
| but NOT permission-gated (every signed-in user reports their own time).
*/
Route::middleware(['auth', 'cors'])->prefix('visitor-history')->group(function () {
    Route::get('/', [VisitorHistoryController::class, 'index'])
        ->name('visitor-history')
        ->middleware('can:read visitor-history');
    Route::get('/sessions/{visitorSessionId}/page-views', [VisitorHistoryController::class, 'pageViews'])
        ->name('visitor-history.page-views')
        ->middleware('can:read visitor-history');
    // Returns 204 and never renders a page, so it has no business paying for
    // HandleInertiaRequests::share (which eagerly loads the operator + its logo
    // on every request). Excluded => the beacon is session + auth + 2 queries.
    Route::post('/ping', [VisitorHistoryController::class, 'ping'])
        ->name('visitor-history.ping')
        ->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class]);
});

require __DIR__.'/auth.php';
