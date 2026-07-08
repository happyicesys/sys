<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin "Overwritten status" override.
 *
 * Lets an admin FORCE a refund ticket's status (e.g. flip an Approved ticket to
 * Rejected) from the Overwritten section on the ticket page. The forced status is
 * written straight to the `status` column (Dropped is stored as Rejected +
 * is_dropped, mirroring the normal Drop action) and NO email is sent to the
 * customer. These columns keep the admin's justification and the time of the last
 * override so the section can display it; the full change is also written to the
 * audit trail (refund_ticket_logs, action = status_override) on one line.
 *
 * Both columns are NULL for untouched tickets — no backfill needed and no effect
 * on any existing payout / settlement behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            // Admin note explaining the forced status change (optional).
            $table->text('status_override_remarks')->nullable()->after('final_refund_remarks');
            // When the status was last force-changed (NULL = never overridden).
            $table->timestamp('status_overridden_at')->nullable()->after('status_override_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            $table->dropColumn(['status_override_remarks', 'status_overridden_at']);
        });
    }
};
