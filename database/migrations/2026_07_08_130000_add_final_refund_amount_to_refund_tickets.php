<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-adjustable final refund amount.
 *
 * The customer submits claimed_amount_cents on the public form, but that figure is
 * sometimes wrong (e.g. they key $5 when only $3 is claimable). These columns let
 * an admin record the amount we will ACTUALLY pay out, without mutating the
 * customer's original claim (kept intact for audit / the Customer Submission panel).
 *
 * final_refund_amount_cents is NULL until an admin sets it; the payout path reads
 * COALESCE(final_refund_amount_cents, claimed_amount_cents) so existing/untouched
 * tickets behave exactly as before (no backfill needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            // NULL = not overridden -> fall back to claimed_amount_cents at payout.
            $table->integer('final_refund_amount_cents')->nullable()->after('claimed_amount_cents');
            // Optional admin note explaining the adjustment.
            $table->text('final_refund_remarks')->nullable()->after('final_refund_amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            $table->dropColumn(['final_refund_amount_cents', 'final_refund_remarks']);
        });
    }
};
