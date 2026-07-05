<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            // "Dropped / double submission" close-out: the ticket is marked Rejected
            // (reusing the existing status) but flagged as dropped so the RF table can
            // strike a line through it instead of deleting it. No customer email is sent.
            $table->boolean('is_dropped')->default(false)->after('auto_refund_detected');

            // Email threading: the Message-ID of the FIRST delivered customer email
            // (the acknowledgement) becomes the thread root. Every later workflow email
            // (approval, auto-refund-triggered, etc.) is sent as a reply referencing it
            // so the customer sees one continuous thread. Null until a real email is
            // delivered (delivery is gated by REFUND_EMAIL_ENABLED).
            $table->string('email_message_id', 255)->nullable()->after('last_email_sent_at');
            // The subject of that first email — reused (as "Re: ...") on replies so the
            // thread stays visually grouped in mail clients that key on subject too.
            $table->string('email_thread_subject', 255)->nullable()->after('email_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            $table->dropColumn(['is_dropped', 'email_message_id', 'email_thread_subject']);
        });
    }
};
