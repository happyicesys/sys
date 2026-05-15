<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds last-edited audit columns for `customers.ops_note`. Mirrors the
 * (notes, notes_updated_at, notes_updated_by) trio added earlier for the
 * Customer Note feature — same shape, same nullOnDelete behaviour — so
 * the Vend/CustomerIndex page can show a "Brian (260515 04:11pm)" line
 * under the Ops Note textarea exactly like it does under Customer Note.
 *
 * The audit pair is consistent with contract_detail_updated_at/by and
 * notes_updated_at/by, which is the established pattern on this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('ops_note_updated_at')->nullable()->after('ops_note');
            $table->foreignId('ops_note_updated_by')->nullable()->after('ops_note_updated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['ops_note_updated_by']);
            $table->dropColumn(['ops_note_updated_at', 'ops_note_updated_by']);
        });
    }
};
