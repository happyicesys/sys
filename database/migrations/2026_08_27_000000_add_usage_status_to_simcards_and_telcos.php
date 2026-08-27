<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Telco-API usage status for the Simcard Index "Status" column.
 *
 * telcos.usage_provider names the SimcardUsageProvider (config
 * simcard_usage.providers key) that can report live status for that telco's
 * sims — null = no API, the cron skips them. simcards.usage_* is the latest
 * snapshot pulled by simcards:sync-usage (package status / active / expire /
 * used MB, plus when we last successfully synced that sim).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telcos', function (Blueprint $table) {
            $table->string('usage_provider')->nullable()->after('name');
        });

        Schema::table('simcards', function (Blueprint $table) {
            $table->string('usage_status')->nullable()->after('termination_date');
            $table->datetime('usage_active_at')->nullable()->after('usage_status');
            $table->datetime('usage_expire_at')->nullable()->after('usage_active_at');
            $table->decimal('usage_used_mb', 10, 2)->nullable()->after('usage_expire_at');
            $table->datetime('usage_synced_at')->nullable()->after('usage_used_mb');
        });

        // The VoicePing packages are the first telco with an API. Forward-only
        // backfill; new telcos opt in by setting usage_provider on their row.
        DB::table('telcos')
            ->where('name', 'LIKE', 'VoicePing%')
            ->update(['usage_provider' => 'voiceping']);
    }

    public function down(): void
    {
        Schema::table('simcards', function (Blueprint $table) {
            $table->dropColumn([
                'usage_status', 'usage_active_at', 'usage_expire_at',
                'usage_used_mb', 'usage_synced_at',
            ]);
        });

        Schema::table('telcos', function (Blueprint $table) {
            $table->dropColumn('usage_provider');
        });
    }
};
