<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes 4 redundant indexes from vend_temps and 1 from vend_smart_alerts.
 *
 * Background: Over several migration cycles, duplicate and subsumed indexes
 * accumulated on vend_temps — a high-frequency write table that receives a
 * temperature reading from every active machine every few minutes. Maintaining
 * 8 indexes per INSERT was a significant write-amplification cost that slowed
 * down the queue workers running DetectTempTrends.
 *
 * Indexes dropped and why:
 *
 * vend_temps:
 *   idx_vend_type_created      (vend_id, type, created_at)         — exact duplicate of idx_vt_vid_type_created
 *   idx_type_created_vend      (type, created_at, vend_id)          — exact duplicate of idx_vend_temps_type_created_vend
 *   idx_vend_type_value        (vend_id, type, value)               — prefix of idx_vt_optimal_trend (vend_id, type, value, created_at)
 *   idx_vt_vid_created         (vend_id, created_at)                — prefix of idx_vt_vid_type_created (vend_id, type, created_at)
 *
 * vend_smart_alerts:
 *   idx_vend_alert_type        (vend_id, alert_type)                — exact duplicate of idx_vsa_vid_atype
 *
 * Indexes kept on vend_temps (4 remain):
 *   idx_vt_vid_type_created    (vend_id, type, created_at)          — per-vend range scans
 *   idx_vt_optimal_trend       (vend_id, type, value, created_at)   — covering index for ORDER BY value queries
 *   idx_vend_temps_type_created_vend  (type, created_at, vend_id)   — cross-vend time-range queries
 *   idx_vt_type_vend_created   (type, vend_id, created_at)          — cross-vend per-machine queries
 */
return new class extends Migration {

    public function up(): void
    {
        // --- vend_temps: drop 4 redundant indexes ---

        $this->dropIfExists('vend_temps', 'idx_vend_type_created');        // dup of idx_vt_vid_type_created
        $this->dropIfExists('vend_temps', 'idx_type_created_vend');        // dup of idx_vend_temps_type_created_vend
        $this->dropIfExists('vend_temps', 'idx_vend_type_value');          // prefix of idx_vt_optimal_trend
        $this->dropIfExists('vend_temps', 'idx_vt_vid_created');           // prefix of idx_vt_vid_type_created

        // --- vend_smart_alerts: drop 1 redundant index ---

        $this->dropIfExists('vend_smart_alerts', 'idx_vend_alert_type');   // dup of idx_vsa_vid_atype
    }

    public function down(): void
    {
        Schema::table('vend_temps', function (Blueprint $table) {
            $table->index(['vend_id', 'type', 'created_at'], 'idx_vend_type_created');
            $table->index(['type', 'created_at', 'vend_id'],  'idx_type_created_vend');
            $table->index(['vend_id', 'type', 'value'],        'idx_vend_type_value');
            $table->index(['vend_id', 'created_at'],           'idx_vt_vid_created');
        });

        Schema::table('vend_smart_alerts', function (Blueprint $table) {
            $table->index(['vend_id', 'alert_type'], 'idx_vend_alert_type');
        });
    }

    private function dropIfExists(string $table, string $indexName): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();

        if ($exists) {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        }
    }
};
