<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How long the Mac took to count the session (Brian, 2026-11-19: "include how long it takes to calculate"). The Mac
 * sends it as `detail.seconds` (its per-stage split stays in `answer.detail.timing`); `latency_ms` is a different thing,
 * the wait from the session's first video reaching us to the answer. Rows already in carry it in `answer`, so they are
 * filled from there. NULL = the answer came from the nightly run, which counts many sessions at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smart_freezer_own_ai_recognitions', function (Blueprint $table) {
            $table->unsignedInteger('compute_ms')->nullable()->after('latency_ms');
        });

        DB::table('smart_freezer_own_ai_recognitions')->orderBy('id')->select(['id', 'answer'])->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $seconds = (json_decode((string) $row->answer, true) ?: [])['detail']['seconds'] ?? null;
                if (is_numeric($seconds) && $seconds >= 0 && $seconds <= 86400) {
                    DB::table('smart_freezer_own_ai_recognitions')->where('id', $row->id)->update(['compute_ms' => (int) round($seconds * 1000)]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('smart_freezer_own_ai_recognitions', function (Blueprint $table) {
            $table->dropColumn('compute_ms');
        });
    }
};
