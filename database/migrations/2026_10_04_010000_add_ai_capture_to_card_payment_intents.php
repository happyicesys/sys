<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-decided charge on a T05 hold (2026-10-03): the door-closed capture names the kiosk
 * session (`session_ref` = the TRADE's SFREF = smart_freezer_recognitions.session_ref), and
 * the hold waits for that session's verdict. `ai_decision` keeps what was decided and why;
 * `owed_cents` is what the AI judged that is not charged yet (further charges pending or refused).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_payment_intents', function (Blueprint $table) {
            $table->string('session_ref', 64)->nullable()->after('custom_order_id')->index();
            $table->timestamp('door_closed_at')->nullable()->after('approved_at');
            $table->json('ai_decision')->nullable()->after('last_response');
            $table->unsignedInteger('owed_cents')->nullable()->after('captured_cents');
        });
    }

    public function down(): void
    {
        Schema::table('card_payment_intents', function (Blueprint $table) {
            $table->dropIndex(['session_ref']);
            $table->dropColumn(['session_ref', 'door_closed_at', 'ai_decision', 'owed_cents']);
        });
    }
};
