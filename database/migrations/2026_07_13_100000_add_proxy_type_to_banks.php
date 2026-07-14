<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PayNow proxy type for the pseudo-bank rows (Paynow, Paynow (UEN), …). CIMB
 * detail column E is either the beneficiary BIC (real bank, account transfer) or
 * a PayNow proxy type (MOB / NRIC / UEN / VPA). Real banks leave this null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('proxy_type', 8)->nullable()->after('bic_code');
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('proxy_type');
        });
    }
};
