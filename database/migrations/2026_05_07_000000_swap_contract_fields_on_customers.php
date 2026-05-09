<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replace `contract_min_commitment_period` with two new columns on the
 * placement-contract block:
 *
 *  - `contract_from`     (date, nullable) — start date of the placement contract
 *  - `contract_remarks`  (text, nullable) — free-form remarks on the contract
 *
 * Mirrors the same change on `customer_contract_logs` so historical snapshots
 * stay consistent with the live customer record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add new fields. Anchor `contract_from` next to `contract_until`.
            if (!Schema::hasColumn('customers', 'contract_from')) {
                $table->date('contract_from')->nullable()->after('contract_ps_term');
            }
            if (!Schema::hasColumn('customers', 'contract_remarks')) {
                $table->text('contract_remarks')->nullable()->after('contract_notice_period');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'contract_min_commitment_period')) {
                $table->dropColumn('contract_min_commitment_period');
            }
        });

        Schema::table('customer_contract_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_contract_logs', 'contract_from')) {
                $table->date('contract_from')->nullable()->after('contract_ps_term');
            }
            if (!Schema::hasColumn('customer_contract_logs', 'contract_remarks')) {
                $table->text('contract_remarks')->nullable()->after('contract_notice_period');
            }
        });

        Schema::table('customer_contract_logs', function (Blueprint $table) {
            if (Schema::hasColumn('customer_contract_logs', 'contract_min_commitment_period')) {
                $table->dropColumn('contract_min_commitment_period');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'contract_min_commitment_period')) {
                $table->unsignedSmallInteger('contract_min_commitment_period')->nullable()->after('contract_auto_renewal');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'contract_remarks')) {
                $table->dropColumn('contract_remarks');
            }
            if (Schema::hasColumn('customers', 'contract_from')) {
                $table->dropColumn('contract_from');
            }
        });

        Schema::table('customer_contract_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_contract_logs', 'contract_min_commitment_period')) {
                $table->unsignedSmallInteger('contract_min_commitment_period')->nullable()->after('contract_auto_renewal');
            }
        });

        Schema::table('customer_contract_logs', function (Blueprint $table) {
            if (Schema::hasColumn('customer_contract_logs', 'contract_remarks')) {
                $table->dropColumn('contract_remarks');
            }
            if (Schema::hasColumn('customer_contract_logs', 'contract_from')) {
                $table->dropColumn('contract_from');
            }
        });
    }
};
