<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convert `contract_notice_period` from unsignedSmallInteger (months) to a
 * short string column that holds the picked dropdown option directly
 * ('1 wk', '2 wk', '3 wk', '1 mth', '1.5 mth', '2 mth', '3 mth', 'NO need',
 * 'Cant ETerm').
 *
 * Per user direction: wipe existing numeric data — the new master Excel
 * supplies authoritative values for every customer via the seeder, and the
 * old integer column never represented fractional weeks anyway (0.25, 0.5
 * were silently truncated to 0). Mirrored on `customer_contract_logs` to keep
 * the audit-trail schema consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('contract_notice_period');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->string('contract_notice_period', 16)
                ->nullable()
                ->after('contract_auto_renewal');
        });

        Schema::table('customer_contract_logs', function (Blueprint $table) {
            $table->dropColumn('contract_notice_period');
        });
        Schema::table('customer_contract_logs', function (Blueprint $table) {
            $table->string('contract_notice_period', 16)
                ->nullable()
                ->after('contract_auto_renewal');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('contract_notice_period');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('contract_notice_period')
                ->nullable()
                ->after('contract_auto_renewal');
        });

        Schema::table('customer_contract_logs', function (Blueprint $table) {
            $table->dropColumn('contract_notice_period');
        });
        Schema::table('customer_contract_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('contract_notice_period')
                ->nullable()
                ->after('contract_auto_renewal');
        });
    }
};
