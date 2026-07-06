<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repeat-submission marker.
 *
 * The public /refund form no longer BLOCKS a second submission for a transaction
 * that already has an active claim — the customer is allowed to resubmit. Instead
 * the new ticket is flagged here as a repeat (with the original reference kept for
 * context) so an admin re-validates it against the earlier request rather than the
 * system silently rejecting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            // false = new request, true = repeat of an already-active claim
            $table->boolean('is_repeat')->default(false)->after('submit_ip');
            // reference of the earliest active claim this one duplicates (nullable)
            $table->string('replicated_from_reference')->nullable()->after('is_repeat');
        });
    }

    public function down(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            $table->dropColumn(['is_repeat', 'replicated_from_reference']);
        });
    }
};
