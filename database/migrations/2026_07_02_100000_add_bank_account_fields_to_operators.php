<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-operator bank originator details for bank bulk-transfer exports
 * (e.g. CIMB BizChannel header: account no + registered account name).
 * Falls back to config('refund.banks.*') env values when blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->string('bank_account_no', 30)->nullable()->after('gst_vat_rate');
            $table->string('bank_account_name', 120)->nullable()->after('bank_account_no');
        });
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropColumn(['bank_account_no', 'bank_account_name']);
        });
    }
};
