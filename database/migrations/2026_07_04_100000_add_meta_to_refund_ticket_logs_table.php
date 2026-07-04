<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_ticket_logs', function (Blueprint $table) {
            // Structured payload for a log line — used by email entries to carry
            // the recipient / subject / body so the audit trail can pop it open.
            $table->json('meta')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('refund_ticket_logs', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
