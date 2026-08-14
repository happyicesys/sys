<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw inbound CityBox-Openapi webhook pushes (order / refund / close-door).
 *
 * Design: STORE FIRST, PROJECT LATER. Each push is persisted verbatim
 * (payload = their `data` JSON decoded, raw_data = the exact string that was
 * signed) before we ack success — their retry loop is our delivery
 * guarantee, so the ack must mean "durably stored", nothing more. Projection
 * into vend_transactions / channel deduction happens in a later phase, off
 * processed_at, once currency (SGD/CNY) and the chiller planogram are
 * settled — that is why amounts are NOT normalised into columns here.
 *
 * Idempotency: UNIQUE (type, event_key). Their doc says pushes repeat until
 * acked (order/refund) so duplicates are expected, not exceptional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citybox_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // order | refund | close
            $table->string('event_key', 128);
            // Matched via box_no → vends.citybox_equipment_id at ingest; nullable
            // because an unknown device must still be stored (never dropped).
            // Deliberately NO foreign key: this is an append-only audit ledger of
            // what Citybox sent us, and deleting a vend must never cascade into
            // (or be blocked by) stored webhook history.
            $table->unsignedBigInteger('vend_id')->nullable()->index();
            $table->json('payload');
            $table->text('raw_data'); // exact signed bytes, for audit/re-verification
            // Which sign concatenation variant matched (their doc is ambiguous);
            // null = signature did NOT verify (event kept for forensics, never processed).
            $table->string('signature_variant', 20)->nullable();
            $table->timestamp('processed_at')->nullable()->index(); // phase-2 projection marker
            $table->timestamps();

            $table->unique(['type', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citybox_webhook_events');
    }
};
