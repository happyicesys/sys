<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only mirror columns pulled from CMS (sys.happyice) for vending
 * customers that are linked by `customers.person_id`.
 *
 * These four scalars have no existing home on the customer record:
 *  - `company_remark` — CMS `com_remark` (the "Company" free-text on the
 *                       CMS person form; NOT the same as `customers.name`,
 *                       which mirrors CMS `company`).
 *  - `site_name`      — CMS `site_name` ("Delivery Location Name").
 *  - `cost_rate`      — CMS `cost_rate` (percentage, e.g. 100.00).
 *  - `payterm`        — CMS `payterm` (Terms label string, e.g.
 *                       "15 Days after EOM").
 *
 * The remaining mirrored fields reuse existing storage and need no columns:
 *  - billing address  → `addresses` (type = 1)
 *  - delivery address → `addresses` (type = 2)  [already synced today]
 *  - email / alt phone→ `contacts`
 *
 * Populated only by the UpdateCustomerCmsFields job/seeder (update-only,
 * never creates customers, never overwrites fields owned by
 * SyncVendCustomerCms). Surfaced read-only on Customer/Edit.vue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'company_remark')) {
                $table->string('company_remark')->nullable()->after('name');
            }
            if (!Schema::hasColumn('customers', 'site_name')) {
                $table->string('site_name')->nullable()->after('company_remark');
            }
            if (!Schema::hasColumn('customers', 'cost_rate')) {
                $table->decimal('cost_rate', 8, 2)->nullable()->after('site_name');
            }
            if (!Schema::hasColumn('customers', 'payterm')) {
                $table->string('payterm', 64)->nullable()->after('cost_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (['payterm', 'cost_rate', 'site_name', 'company_remark'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
