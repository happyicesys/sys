<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BIC / SWIFT code per bank — needed by the CIMB BizChannel bulk payment
 * file (detail column E carries the beneficiary bank's BIC for account
 * transfers). Maintained under Data Management → Banks; seeded for the SG
 * list from App\Services\Banking\CimbBankDirectory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('bic_code', 11)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('bic_code');
        });
    }
};
