<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the "External Subsidize" pair to the placement-contract block:
 *
 *  - `is_external_subsidize`   (boolean) — toggle that gates whether the
 *                                          amount below is editable / used.
 *  - `external_subsidize_amount` (decimal 10,2, nullable) — dollar amount,
 *                                          only meaningful when the toggle is on.
 *
 * Mirrored on `customer_contract_logs` so historical contract snapshots stay
 * consistent with the live customer record (same pattern as the other
 * contract_* fields).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'is_external_subsidize')) {
                $table->boolean('is_external_subsidize')->default(false)->after('contract_commission_value2');
            }
            if (!Schema::hasColumn('customers', 'external_subsidize_amount')) {
                $table->decimal('external_subsidize_amount', 10, 2)->nullable()->after('is_external_subsidize');
            }
        });

        Schema::table('customer_contract_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_contract_logs', 'is_external_subsidize')) {
                $table->boolean('is_external_subsidize')->default(false)->after('contract_commission_value2');
            }
            if (!Schema::hasColumn('customer_contract_logs', 'external_subsidize_amount')) {
                $table->decimal('external_subsidize_amount', 10, 2)->nullable()->after('is_external_subsidize');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'external_subsidize_amount')) {
                $table->dropColumn('external_subsidize_amount');
            }
            if (Schema::hasColumn('customers', 'is_external_subsidize')) {
                $table->dropColumn('is_external_subsidize');
            }
        });

        Schema::table('customer_contract_logs', function (Blueprint $table) {
            if (Schema::hasColumn('customer_contract_logs', 'external_subsidize_amount')) {
                $table->dropColumn('external_subsidize_amount');
            }
            if (Schema::hasColumn('customer_contract_logs', 'is_external_subsidize')) {
                $table->dropColumn('is_external_subsidize');
            }
        });
    }
};
