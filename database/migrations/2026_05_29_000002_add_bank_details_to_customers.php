<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-customer bank details captured in the "Bank Details" section of
 * Customer Create/Edit: a bank reference plus account name/number (both
 * stored as strings — account numbers can carry leading zeros / be long).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable()->index()->after('is_billing_same_as_delivery');
            $table->string('bank_account_name')->nullable()->after('bank_id');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['bank_id', 'bank_account_name', 'bank_account_number']);
        });
    }
};
