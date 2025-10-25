<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('vend_logs')) {
            return;
        }

        Schema::table('vend_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('vend_logs', 'vend_id')) {
                return;
            }

            $table->index(['vend_id', 'occurred_at'], 'vend_logs_vend_id_occurred_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('vend_logs')) {
            return;
        }

        Schema::table('vend_logs', function (Blueprint $table) {
            $table->dropIndex('vend_logs_vend_id_occurred_at_index');
        });
    }
};
