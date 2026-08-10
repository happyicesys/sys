<?php

namespace Database\Seeders;

use DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * THE SINGLE SOURCE OF TRUTH FOR ROLES AND PERMISSIONS.
 * =====================================================
 * Every permission the app checks, and every role that holds it, is declared in
 * the $permissionsData table below. To change access, amend that table - do not
 * write a new one-off seeder and do not seed permissions from a migration. Both
 * of those get silently undone the next time this runs, because this rebuilds
 * the whole set from scratch.
 *
 * 2026-08-10: the historical one-off seeders are RETIRED as no-ops for exactly
 * that reason — ProdOwnerPermissionsSeeder (would strip prod_owner back to its
 * old 12 grants) and DashboardPerformanceLitePermissionSeeder (would revoke
 * 'read/export dashboard-performance' the 2026-08-09 sheet sync grants). The
 * older PermissionSeeder / ExportPermissionSeeder / CustomerPermissionSeeder /
 * ObserverRoleSeeder era predates this seeder and is equally superseded.
 *
 *     php artisan db:seed --class=RolePermissionSyncSeeder
 *
 * HOW TO AMEND
 *   - new permission : add a tuple  ['thing', ['read','export'], ['role', ...]]
 *   - grant to a role: add the role name to that tuple's third array
 *   - new role       : just name it - roles are created automatically (below)
 *   - revoke         : remove the role from the tuple. Removing a tuple deletes
 *                      the permission itself, so anything still checking it
 *                      starts 403ing. Grep first.
 *
 * SAFETY PROPERTIES (relied on - keep them true)
 *   - ATOMIC, in the DB *and* in Spatie's cache. The unbind / delete / rebuild
 *     all happen in ONE transaction (delete(), not truncate(): TRUNCATE is DDL,
 *     implicitly commits, and would dissolve the transaction around it) AND the
 *     registrar is pointed at a process-local 'array' store for the duration.
 *     Both halves are needed: the cache is what actually decides access, it is
 *     not transactional, and Permission::create() publishes the map it reads.
 *     Restoring the real store and flushing it happens in a finally block, so a
 *     failed run cannot leave a half-built map cached for 24h.
 *   - ROLES ARE CREATED, not skipped. The old code looked a role up and skipped
 *     it when absent, which is exactly how prod_owner's grants vanished - the
 *     tuples named a role nothing had created and every grant was dropped in
 *     silence. A role that had to be created is reported on the console; if you
 *     see one you did not expect, it is a typo in a tuple.
 *   - Direct per-user grants (model_has_permissions) are DESTROYED, not skipped:
 *     that FK is ON DELETE CASCADE, and a query-builder mass delete fires no
 *     model events, so they vanish with no error and no console line. There are
 *     none today and there should stay none - grant through a role.
 *
 * Changes apply on the next page load - no logout needed. HandleInertiaRequests
 * shares permissions from a plain closure in share(), which Inertia evaluates on
 * every response. The real caveat is different: that closure reads
 * roles->first()->permissions, so a user with TWO roles only ever sees the
 * first role's permissions in the Vue sidebar.
 */
class RolePermissionSyncSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nothing is unbound or deleted here any more - the whole rebuild is one
        // transaction at the bottom of this method, so there is no window where a
        // live user holds zero permissions. See the class docblock.
        //
        // Define permissions and link them to roles based on the data from the file
        $permissionsData = [
            [
                'mcp-tokens',
                ['read', 'manage'],
                ['superadmin', 'admin']
            ],
            [
                'visitor-history',
                ['read'],
                // 2026-08-01: superadmin ONLY. Login sessions / IP / device / per-page
                // dwell time for every user is audit data, so it stays off the admin
                // tier. Keep 'superadmin' listed even though Gate::before already lets
                // it pass - HandleInertiaRequests shares the ROLE's permission rows, so
                // dropping it here would hide the sidebar item from superadmin too.
                ['superadmin']
            ],
            [
                'dashboard',
                ['read', 'export'],
                // 2026-08-09 sheet sync: + prod_owner, together with the full
                // "Dashboard > Performance" grant on the tuple below (see the
                // warning there — this is a deliberate widening; the role also
                // keeps dashboard-performance-lite, so both links render).
                // Before 2026-08-09 prod_owner was deliberately NOT listed here:
                // the sheet's tick was honoured by 'dashboard-performance-lite'
                // alone and the section rendered via Authenticated.vue's
                // ['read dashboard', 'read dashboard-performance-lite'] "any of
                // these" gate. That history matters if the widening is reverted:
                // removing prod_owner here does NOT hide the section for it.
                //
                // 2026-08-10: this seeder is the ONLY place prod_owner (or any
                // role) is granted anything. ProdOwnerPermissionsSeeder and
                // DashboardPerformanceLitePermissionSeeder are retired no-ops.
                ['superadmin', 'admin', 'supervisor', 'observer', 'prod_owner', 'technician', 'operator_admin', 'operator_supervisor', 'licensee', 'hid_user']
            ],

            [
                'dashboard',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin']
            ],

            [
                'dashboard-performance',
                ['read', 'export'],
                // 2026-08-05 sheet sync: + prod_owner
                // 2026-08-06: - prod_owner. Performance (LITE) instead, sourced from
                // vend_product_records so figures are narrowed to their own products.
                // 2026-08-09 sheet sync: + prod_owner AGAIN, per the sheet's
                // "Dashboard > Performance" row, which ticks Prod Owner.
                // READ THIS BEFORE COPYING THE PATTERN: the full Performance page reads
                // vend_records, which has NO product dimension, so prod_owner now sees
                // WHOLE-MACHINE revenue, not just their own products. That is a widening
                // of what 2 live users can see. dashboard-performance-lite is still
                // granted, so both links appear. If the sheet meant "Performance (Lite)",
                // remove prod_owner here and add a Performance (Lite) row to the sheet.
                ['superadmin', 'admin', 'supervisor', 'observer', 'prod_owner', 'operator_admin', 'operator_supervisor', 'licensee', 'hid_user']
            ],

            // Dashboard > Performance (Lite) — /dashboard/performance-lite.
            //
            // Its OWN permission, deliberately NOT `read dashboard-performance`.
            // The Lite page renders the SAME charts through the SAME
            // DashboardController methods, so gating it on the existing
            // permission would have handed it to all nine roles that already
            // have the full page — and still not expressed "Lite but not Full",
            // which is exactly what prod_owner needs. Same reasoning as
            // vend-customers-lite above.
            //
            // prod_owner is auto-created by this seeder like any other role
            // named in a tuple (see the Role::create block below) — no
            // prerequisite seeder needed.
            [
                'dashboard-performance-lite',
                ['read', 'export'],
                ['superadmin', 'admin', 'prod_owner']
            ],

            [
                'dashboard-machine-health',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'vends',
                ['read', 'export'],
                // 2026-07-23 sheet sync v2 (struck-through cells = disabled): - driver
                // (Ops Dashboard Full-Filter Yes struck) and - operator_3pl (limited-filter
                // cell struck; round-1 addition reverted)
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor', 'operator_driver', 'franchisee', 'licensee']
            ],

            [
                'vends',
                ['update'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin']
            ],

            [
                'vends',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'vend-customers',
                ['read', 'export'],
                // 2026-08-09 sheet sync: - driver (Ops Dashboard Full-Filter cell struck) and
                // - operator_3pl (Limited-filter cell struck). `read vends` already
                // excluded both, so the nav link was hidden - but this permission
                // gates the ROUTE, so /vends/customers stayed reachable by URL.
                // - observer_transactions: no column in the sheet (see below).
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor', 'operator_driver', 'franchisee', 'licensee']
            ],

            [
                'vend-contracts',
                ['read', 'export'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (both Yes
                // struck; round-1 operator_supervisor addition reverted)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'vend-customers',
                ['admin-access'],
                // 2026-08-09 sheet sync: - driver. This is the "Full filter" gate
                // (CustomerIndex.vue), and the sheet's Driver cell on Ops Dashboard
                // (Full Filter) is struck through.
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            // Operations > Dashboard (Lite) — /vends/customers-lite. Its OWN
            // permission, deliberately NOT `read vend-customers`.
            //
            // The page renders the SAME rows as the full Operation Dashboard
            // through the same VendController::indexCustomer, so gating it on
            // `read vend-customers` would have handed the glance view to all
            // twelve roles that already have the full page — and still not
            // reached prod_owner, which the sheet puts on Lite and nowhere near
            // the full Dashboard. A separate permission is the only way to
            // express "Lite but not Full".
            //
            // prod_owner is auto-created by this seeder like any other role
            // named in a tuple (see the Role::create block below) — no
            // prerequisite seeder needed.
            [
                'vend-customers-lite',
                ['read', 'export'],
                ['superadmin', 'admin', 'prod_owner']
            ],

            [
                'vend-machines',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'vend-machines',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'transactions',
                ['read', 'export'],
                // 2026-08-05 sheet sync: + prod_owner (All Transaction, LIMITED
                // filter). "Limited" == read without admin-access: Transaction.vue
                // gates ~10 filter controls on 'admin-access transactions', so
                // prod_owner must NOT appear on the admin-access tuple below.
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor', 'franchisee', 'licensee', 'hid_user', 'prod_owner']
            ],

            [
                'transactions',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'transactions-sales',
                ['read', 'export'],
                // 2026-08-05 sheet sync: + prod_owner
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor', 'franchisee', 'licensee', 'hid_user', 'prod_owner']

            ],

            [
                // NEW 2026-08-05. Daily Summary used to share 'read
                // transactions-sales' with All Transactions, so the two could not
                // be granted separately - but the sheet gives prod_owner All
                // Transactions and NOT Daily Summary. Role list is exactly whoever
                // holds read transactions-sales today MINUS prod_owner, so this is
                // behaviour-preserving for every existing role.
                'transactions-daily-summary',
                ['read', 'export'],
                // 2026-08-09 sheet sync: the sheet's Daily Summary row is superadmin/admin/supervisor
                // ONLY. The 2026-08-05 split copied `read transactions-sales`
                // wholesale to stay behaviour-preserving and was never reconciled to
                // that row. Removed: technician, operator_admin, operator_supervisor,
                // franchisee, licensee, hid_user, observer_transactions.
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'transactions-sales',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'transactions-payment-gateway',
                ['read', 'export'],
                // 2026-07-23 sheet sync: - operator_admin, operator_supervisor (HappyIce staff only)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'operations',
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl']
            ],

            [
                'operation-jobs',
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl']
            ],

            [
                'operation-job-summaries',
                ['read', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: HappyIce staff only - operator_admin/
                // operator_supervisor/operator_driver Yes struck through, operator_3pl blank
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver']
            ],

            [
                'vend-settings',
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor', 'production_jb']
            ],

            [
                'machine-view',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'production_jb', 'operator_admin', 'operator_supervisor']
            ],

            [
                'machine-settings',
                ['read', 'update'],
                // 2026-07-23 sheet sync: - operator_admin, operator_supervisor (Machine Settings = HappyIce staff only).
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'machine-settings',
                ['export', 'create', 'delete', 'admin-access'],
                // 2026-07-23 sheet sync: - operator_admin, operator_supervisor
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                // 2026-08-09 sheet sync: the sheet lists this page and ticks NO role, so
                // the permission is created and granted to nobody. Deliberately an empty
                // list rather than a deleted tuple: dropping the tuple would delete the
                // permission itself and 403 anything still checking it.
                // Consequence: the sidebar item disappears for EVERYONE including
                // superadmin - HandleInertiaRequests shares the role's permission rows, so
                // Gate::before does not put it back. Superadmin can still reach the page by
                // URL wherever route middleware defers to Gate::before. To restore, put the
                // roles back here.
                'machine-alert-parameters',
                ['read', 'export'],
                []
            ],

            [
                // APK OTA Updates (/apk-releases). Deliberately NOT sharing
                // machine-settings: uploading and publishing a binary pushes code to
                // every machine on a channel, which is a far wider blast radius than
                // editing a machine's settings. Kept to staff who own releases.
                // 2026-07-31 code-side addition — add this row to the Google Sheet so
                // the next sheet sync does not drop it.
                'apk-releases',
                ['read', 'create', 'update', 'delete'],
                ['superadmin', 'admin']
            ],

            [
                'apk-settings',
                ['read', 'export'],
                // 2026-07-23 sheet sync: - driver (UI Setting row has no Driver)
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'vend-configs',
                ['read', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'vend-prefixes',
                ['read', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'cashless-terminals',
                ['read', 'create', 'update', 'delete', 'admin-access'],
                // 2026-07-23 sheet sync: - technician; v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'customers',
                ['read', 'export', 'create', 'update', 'delete'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'customers',
                ['admin-access'],
                // 2026-07-23 sheet sync: - operator_admin, operator_supervisor (Site Settlement = staff only)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'products',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'product-mappings',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'product-availability',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'product-categories',
                ['read'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'product-categories',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'product-subcategories',
                ['read'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'product-subcategories',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'product-campaign-labels',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Product
                // Labels row struck).
                // 2026-08-09: this permission NO LONGER gates Campaign Management - see
                // the 'campaigns' tuple below. It now means exactly one thing: Product
                // Management > Product Labels (/tags?classname=Product). The old comment
                // here said the operator removal "also hides Campaign Management menu";
                // that side effect was the bug, not the intent.
                ['superadmin', 'admin', 'supervisor']
            ],

            // Campaign Management (/campaigns) - its OWN permission, split out of
            // 'product-campaign-labels' on 2026-08-09.
            //
            // One permission cannot serve both: the sheet puts Product Labels at
            // superadmin/admin/supervisor but Campaign Management > Settings at
            // superadmin/admin/supervisor/technician/operator_admin/operator_supervisor.
            // Granting the shared permission wide would have handed Product Labels to the
            // operator roles; leaving it narrow kept the whole Campaign Management SECTION
            // hidden from the three roles that already held every 'vouchers' permission -
            // 33 live users holding grants that rendered nothing.
            //
            // NOTE the sheet lists this page twice: 'Machine Campaigns' under Product
            // Management (superadmin/admin/supervisor) and 'Settings' under Campaign
            // Management (+technician/operator_admin/operator_supervisor). There is only
            // one campaign page (CampaignController -> Pages/Campaign), so the two rows
            // contradict. Taking the Campaign Management block as authoritative: it is the
            // newer entry, and it is the one the section is named after. If that is wrong,
            // narrow the list here and delete the Campaign Management rows from the sheet.
            [
                // Every action goes to exactly the roles ticked on the sheet's
                // "Campaign Management > Settings" row. admin-access is included rather
                // than held back at the staff tier because nothing in the codebase checks
                // 'admin-access campaigns' - splitting it would encode a distinction the
                // sheet does not make and no code enforces.
                'campaigns',
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'data-settings',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (whole
                // Data Management section struck through for the operator roles)
                ['superadmin', 'admin', 'supervisor', 'technician', 'hid_user']
            ],

            [
                'card-terminals',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'machine-stickers',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (page not in
                // sheet, but whole Data Management section is disabled for operator roles)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'location-types',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'banks',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (page not in
                // sheet, but whole Data Management section is disabled for operator roles)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'hid-cards',
                ['read', 'create', 'update', 'delete', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (both Yes
                // struck; round-1 operator_supervisor addition reverted); hid_user keeps access
                ['superadmin', 'admin', 'supervisor', 'technician', 'hid_user']
            ],

            [
                'vend-models',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'modem-models',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            // 2026-07-23 sheet sync v2: Modem IMEI appears twice in the sheet - the
            // Machine Mgmt row adds technician read; the Data Mgmt row's operator grants
            // are struck through (disabled), so operators get nothing here.
            // Write actions stay superadmin/admin/supervisor.
            [
                'modem-imei',
                ['read'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'modem-imei',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'keys',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'serial-numbers',
                ['read'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'serial-numbers',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'telcos',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'simcards',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'zones',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'delivery-platforms',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'delivery-platform-orders',
                ['read', 'export', 'update'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'delivery-platform-orders',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'delivery-platform-vends',
                ['read', 'export', 'update'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'delivery-platform-vends',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'delivery-platform-product-mappings',
                ['read', 'export', 'create', 'update', 'delete'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck);
                // Delivery Campaign row is NOT struck so delivery-platform-campaigns keeps them
                // 2026-07-29: operator_admin re-granted CRUD on /delivery-product-mappings
                // (requested by brian). admin-access intentionally NOT granted - see block below.
                ['superadmin', 'admin', 'supervisor', 'operator_admin']
            ],

            [
                'delivery-platform-product-mappings',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'delivery-platform-campaigns',
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                // Section gate + the three report pages the sheet gives the operator
                // roles: Stock Count Dashboard, Daily Stock Count, Sales Report.
                'reports',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                // Margin reports - split off 'reports' on 2026-08-09. The sheet grades the
                // eight Report rows differently: Stock Count Dashboard / Daily Stock Count /
                // Sales Report tick operator_admin + operator_supervisor, while Machine
                // Monthly Snapshot and the four GP reports are superadmin/admin/supervisor.
                // The nav children carried NO permission field at all, so all eight
                // inherited 'read reports' and 30 operator-side users could read
                // gross-profit figures.
                //
                // Covers: /reports/snapshot, /reports/gp/vend, /reports/gp/product,
                // /reports/gp/category, /reports/gp/location-type, and
                // /reports/sales-performance/product. That last one has no sheet row; it is
                // product-grained margin data, so it is grouped here rather than left open.
                'reports-gp',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'admins',
                ['read', 'export'],
                ['superadmin', 'admin', 'operator_admin']
            ],

            [
                'operators',
                ['create', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin']
            ],

            [
                'operators',
                ['read', 'update'],
                // 2026-07-23 sheet sync: - operator_admin (Admin > Operators = superadmin/admin only)
                ['superadmin', 'admin']
            ],

            [
                'users',
                ['read', 'create', 'update', 'delete', 'export'],
                ['superadmin', 'admin', 'operator_admin']
            ],

            [
                'users',
                ['admin-access'],
                ['superadmin', 'admin', 'operator_admin']
            ],

            [
                // 2026-08-09 sheet sync: every action goes to exactly the roles ticked on
                // the sheet's "Campaign Management > Voucher" row (+ technician).
                //
                // Reachable as of the same date: the Campaign Management section gate was
                // split off 'read product-campaign-labels' onto ['read campaigns',
                // 'read vouchers'], so holding this permission now actually renders the link.
                //
                // admin-access was previously a separate tuple granted to
                // superadmin/admin/operator_admin - i.e. a customer-side role held it while
                // supervisor, who has full CRUD, did not. Nothing checks
                // 'admin-access vouchers' anywhere in the codebase, so that inversion was
                // invisible rather than intentional. Folded in.
                'vouchers',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'resource-centers',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver']
            ],

            [
                'resource-center-operators',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'resource-center-technicians',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'resource-center-drivers',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver']
            ],

            [
                'tutorials',
                ['read', 'export'],
                // 2026-07-23 sheet sync: - technician, driver (staff use "Tutorial (with CMS)" / resource-centers instead)
                // 2026-08-05 sheet sync: + prod_owner (Tutorial > Management to know)
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl', 'franchisee', 'prod_owner']
            ],

            [
                // Split read/export from admin-access so prod_owner can be given the
                // "Management to know" section (Tutorial/Index.vue gates it on
                // 'read tutorials-operators') without the admin bits.
                'tutorials-operators',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor', 'prod_owner']
            ],

            [
                'tutorials-operators',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'tutorials-technicians',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'tutorials-drivers',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl', 'franchisee']
            ],

            // Refund Requests module. Source of truth = migration
            // 2026_06_26_100004_seed_refund_permissions.php, as amended by
            // 2026_07_03_120001_drop_approve_refunds_permission.php (removed the
            // 'approve refunds' ability, moved supervisors onto 'verify refunds') and
            // 2026_07_11_120000_grant_supervisor_full_refund_access.php (gave
            // supervisor create/update/payout for full parity with admin on the
            // Refund Requests + Refund Settlement pages, e.g. the Re-match box and the
            // settlement actions). Listed here too because this seeder truncates ALL
            // permissions and rebuilds only what it lists — without this block,
            // re-running it would delete refund access. superadmin is granted
            // explicitly so the sidebar (literal permission check) shows it.
            // supervisor now mirrors admin: read/create/update/verify/payout.
            [
                'refunds',
                ['read'],
                // 'operator' removed 2026-08-06. No such role exists (live has
                // operator_admin / operator_supervisor / operator_driver /
                // operator_3pl) and the old lookup-and-skip hid that. Now that
                // roles are auto-created, leaving it here would MANUFACTURE a role
                // named exactly 'operator' - which HasFilter.php:951 and
                // VendChannel.php:178 treat as a customer-binding BYPASS
                // (hasRole('operator') ? 'all' : $isBindedCustomer). One mis-click
                // in user admin would then hand someone every customer.
                ['superadmin', 'admin', 'supervisor']
            ],
            [
                'refunds',
                ['create', 'update', 'payout'],
                ['superadmin', 'admin', 'supervisor']
            ],
            [
                'refunds',
                ['verify'],
                // 'operator' removed - see the read tuple above.
                ['superadmin', 'admin', 'supervisor']
            ],

            // Operator Groups (payout groups) module. Source of truth = migration
            // 2026_07_06_120000_seed_operator_group_permissions.php. Listed here too
            // because this seeder truncates ALL permissions and rebuilds only what it
            // lists — without this block, re-running it would delete the operator-group
            // permissions and 403 the /operator-groups page for admin (superadmin still
            // sees it via Gate::before, but admin would lose access). Admin-only CRUD.
            [
                'operator-groups',
                ['read', 'manage'],
                ['superadmin', 'admin']
            ],
        ];

        // ---- roles -------------------------------------------------------
        // Created if absent, so naming a role in a tuple is genuinely all it
        // takes. Done BEFORE the transaction: a created role is worth keeping
        // even if the rebuild below is rolled back, and it keeps the transaction
        // to just the swap.
        $namedRoles = collect($permissionsData)
            ->flatMap(fn ($data) => $data[2])
            ->unique()
            ->values();

        $createdRoles = [];
        $roleModels = [];

        foreach ($namedRoles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
                $createdRoles[] = $roleName;
            }

            $roleModels[$roleName] = $role;
        }

        // ---- the atomic swap ---------------------------------------------
        // Spatie's permission cache is NOT transactional, and by default it is the
        // SHARED file store. Permission::create() calls getPermission() BEFORE
        // inserting, so each of the 287 creates would publish a half-rebuilt,
        // uncommitted map to the store every live web request authorises against -
        // i.e. the DB would be atomic while the thing that actually decides access
        // was not, and concurrent users would intermittently 403 for the seconds
        // the transaction is open. Worse, a rollback would leave that garbage map
        // cached for the full 24h TTL against a database that never changed.
        //
        // So: point the registrar at a process-local 'array' store for the
        // duration. Web traffic keeps reading the OLD committed map from the file
        // store - consistent with the DB, which is also still old until commit -
        // and the restore + flush in the finally block is what publishes the new
        // state, exactly once, whether we committed or rolled back.
        $originalCacheStore = config('permission.cache.store');
        config(['permission.cache.store' => 'array']);
        app(PermissionRegistrar::class)->initializeCache();

        try {
            DB::transaction(function () use ($permissionsData, $roleModels) {
            foreach (Role::all() as $role) {
                $role->syncPermissions([]);
            }

            // delete(), not truncate() - see the class docblock. role_has_permissions
            // is already empty at this point, so no FK juggling is needed. Both that
            // FK and model_has_permissions' are ON DELETE CASCADE.
            Permission::query()->delete();

            // Load-bearing, not decorative: a query-builder mass delete fires no
            // model events, so it does NOT flush the registrar. Without this the
            // first Permission::create() below finds the just-deleted name still in
            // the cached map and throws PermissionAlreadyExists.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $grants = [];

            foreach ($permissionsData as $data) {
                foreach ($data[1] as $action) {
                    $permissionName = "{$action} {$data[0]}";

                    Permission::create(['name' => $permissionName, 'guard_name' => 'web']);

                    foreach ($data[2] as $roleName) {
                        $grants[$roleName][] = $permissionName;
                    }
                }
            }

            // Spatie resolves permission NAMES against its cached map; the rows
            // above were written inside this transaction, so the map has to be
            // dropped or syncPermissions throws PermissionDoesNotExist.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            // One sync per role rather than a givePermissionTo() per link: ~16
            // statements instead of ~1350, each of which would otherwise flush
            // the permission cache on its own.
            foreach ($grants as $roleName => $permissionNames) {
                $roleModels[$roleName]->syncPermissions(array_unique($permissionNames));
            }
            });
        } finally {
            // finally, not just after the try: DB::transaction() re-throws after
            // rolling back, so on any failure - lock wait, dropped connection - this
            // is the only thing that stops the shared cache being left stale.
            config(['permission.cache.store' => $originalCacheStore]);
            app(PermissionRegistrar::class)->initializeCache();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        // ---- report -------------------------------------------------------
        foreach ($createdRoles as $roleName) {
            $this->command?->warn("  + created role '{$roleName}' - it did not exist. If you did not expect this, it is a typo in a tuple.");
        }

        // Every role is unbound above but only the named ones are re-granted, so a
        // role this table does not mention ends on zero. That is the intended
        // meaning of "single source of truth" - but it must never be silent.
        $orphanRoles = Role::query()
            ->whereNotIn('name', $namedRoles->all())
            ->pluck('name');

        foreach ($orphanRoles as $roleName) {
            $this->command?->warn("  ! role '{$roleName}' is not named in \$permissionsData - it now has ZERO permissions. Add it to a tuple or delete the role.");
        }

        $this->command?->info(sprintf(
            'Rebuilt %d permissions across %d roles. Changes apply on the next page load.',
            collect($permissionsData)->sum(fn ($data) => count($data[1])),
            $namedRoles->count()
        ));
    }
}
