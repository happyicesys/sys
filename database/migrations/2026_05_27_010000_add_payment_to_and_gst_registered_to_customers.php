<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Payment To" tracking on the customer — sys-only (CMS does not record this).
 *
 *  - `payment_to`        (string, nullable) — full company name or personal
 *                         name that Location Fees / commission are paid to.
 *  - `is_gst_registered` (boolean, default false) — whether that payee is
 *                         GST registered.
 *
 * Editable on Customer/Edit.vue for ALL customers (including CMS-linked),
 * since the payee for Location Fees applies to vending customers that are
 * usually linked to CMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'payment_to')) {
                $table->string('payment_to')->nullable()->after('payterm');
            }
            if (!Schema::hasColumn('customers', 'is_gst_registered')) {
                $table->boolean('is_gst_registered')->default(false)->after('payment_to');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'is_gst_registered')) {
                $table->dropColumn('is_gst_registered');
            }
            if (Schema::hasColumn('customers', 'payment_to')) {
                $table->dropColumn('payment_to');
            }
        });
    }
};
