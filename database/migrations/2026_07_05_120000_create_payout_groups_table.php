<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payout group = a higher-level grouping of operators that share ONE originating
 * bank account for bulk transfers (refund settlements, and later commission).
 *
 * Solves the CIMB "one originating account per file" rule for HIPL, which runs
 * several operator IDs (HIMD/LEA/HIESG/UL-ST) but pays refunds from a single
 * account. Third-party operators need no group and keep their own operator bank
 * fields (see refund settlement plan §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // e.g. HIPL — used in the RST reference
            $table->string('name');
            $table->unsignedBigInteger('bank_id')->nullable(); // optional FK -> banks (BIC)
            $table->string('bank_account_no', 34)->nullable(); // the shared CIMB source account
            $table->string('bank_account_name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_groups');
    }
};
