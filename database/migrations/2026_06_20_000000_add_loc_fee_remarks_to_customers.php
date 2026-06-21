<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a free-text "Remarks for Loc Fees" field to customers, surfaced in
 * the rightmost column of the Customer/Site Summary page.
 *
 * Like the existing `notes` field, this is parked on the customer record
 * (not on the monthly customer_period_summaries row) so the remark
 * "follows" the site across any period filter — one box per Site. It is a
 * standalone note dedicated to location-fee context, kept separate from the
 * general Site Note. No unread/mention tracking is attached to this field.
 *
 * Audit columns mirror the notes_updated_at / notes_updated_by pair so the
 * Summary page can render a "last edited by X at T" line and sort by the
 * timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'loc_fee_remarks')) {
                $table->text('loc_fee_remarks')->nullable()->after('notes_updated_by');
            }
            if (!Schema::hasColumn('customers', 'loc_fee_remarks_updated_at')) {
                $table->timestamp('loc_fee_remarks_updated_at')->nullable()->after('loc_fee_remarks');
            }
            if (!Schema::hasColumn('customers', 'loc_fee_remarks_updated_by')) {
                $table->foreignId('loc_fee_remarks_updated_by')->nullable()->after('loc_fee_remarks_updated_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'loc_fee_remarks_updated_by')) {
                $table->dropForeign(['loc_fee_remarks_updated_by']);
            }
            foreach (['loc_fee_remarks', 'loc_fee_remarks_updated_at', 'loc_fee_remarks_updated_by'] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
