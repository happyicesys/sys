<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brian, 2026-11-12: AI Recognition shows how a failed door went — how many tries, how long the customer waited, when
 * the sorry page came. Freezer app 57+ reports that timeline with the failed session: {first_order_at_ms,
 * error_shown_at_ms, steps: [{t_ms, kind, try, text}]}. NULL for reports from older apps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freezer_failed_sessions', function (Blueprint $table) {
            $table->json('door_log')->nullable()->after('items');
        });
    }

    public function down(): void
    {
        Schema::table('freezer_failed_sessions', function (Blueprint $table) {
            $table->dropColumn('door_log');
        });
    }
};
