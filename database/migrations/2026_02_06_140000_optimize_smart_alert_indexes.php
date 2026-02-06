<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Optimize vend_temps queries for DetectTempTrends
        Schema::table('vend_temps', function (Blueprint $table) {
            // (vend_id, type, created_at) matches the exact query pattern:
            // where('vend_id', $id)->where('type', $type)->where('created_at', ...)
            $table->index(['vend_id', 'type', 'created_at'], 'idx_vt_vid_type_created');
        });

        // Optimize vend_smart_alerts lookups
        Schema::table('vend_smart_alerts', function (Blueprint $table) {
            // Used for updateOrCreate(['vend_id', 'alert_type'], ...)
            $table->index(['vend_id', 'alert_type'], 'idx_vsa_vid_atype');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_temps', function (Blueprint $table) {
            $table->dropIndex('idx_vt_vid_type_created');
        });

        Schema::table('vend_smart_alerts', function (Blueprint $table) {
            $table->dropIndex('idx_vsa_vid_atype');
        });
    }
};
