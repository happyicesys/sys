<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who follows the freezer's invoice QR (Brian, 2026-11-05).
 *
 * - `final_seen_at`: when the page first showed this viewer the decided outcome. A T05 amount comes
 *   after the AI, so a scan alone does not say the customer saw it.
 * - `device`: the kind of phone at the first open. Never the raw user agent or the IP address.
 *
 * A row opened before this has both NULL: what it saw was never recorded, so it is not backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freezer_receipts', function (Blueprint $table) {
            $table->timestamp('final_seen_at')->nullable()->after('last_opened_at');
            $table->string('device', 16)->nullable()->after('final_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('freezer_receipts', function (Blueprint $table) {
            $table->dropColumn(['final_seen_at', 'device']);
        });
    }
};
