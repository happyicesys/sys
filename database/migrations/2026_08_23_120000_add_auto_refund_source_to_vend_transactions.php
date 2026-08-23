<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHY the charge was auto-refunded. vend_transactions.is_refunded stays the one
 * boolean every surface reads (Sales Transactions "Auto-refunded?", Refund
 * Request "Auto Refunded?", the validation 3rd icon, the Approve-guard); this
 * column only says which mechanism set it, so ops can tell an Omise API refund
 * from a card-terminal reversal at a glance. Values: App\Support\AutoRefundSource.
 * REFUND_INTEGRITY_AUDIT_2026-08-23.md, Phase 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->string('auto_refund_source', 40)->nullable()->after('is_refunded');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('auto_refund_source');
        });
    }
};
