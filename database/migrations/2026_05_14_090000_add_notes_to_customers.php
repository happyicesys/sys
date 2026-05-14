<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a free-text Notes field to customers, surfaced in the
 * Customer Summary page (Customer Tag column).
 *
 * Parked on the customer record (not on the monthly period-summary
 * row) so the note "follows" the customer across any period filter
 * the user picks on the Summary page — no matter which months are
 * being viewed, the same note shows up.
 *
 * Audit columns mirror the existing contract_detail_updated_at /
 * contract_detail_updated_by pair on the same table so we have a
 * consistent "last edited by X at T" pattern across customer-level
 * editable fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('contract_remarks');
            $table->timestamp('notes_updated_at')->nullable()->after('notes');
            $table->foreignId('notes_updated_by')->nullable()->after('notes_updated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['notes_updated_by']);
            $table->dropColumn(['notes', 'notes_updated_at', 'notes_updated_by']);
        });
    }
};
