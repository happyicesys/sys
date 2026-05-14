<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite index on (vend_code, created_at) for the most common debug query:
 *   "show me logs for machine X in the last N minutes/hours".
 *
 * The single-column index on vend_code already exists, but cannot be efficiently
 * combined with a created_at range scan. This composite index covers both.
 *
 * NOTE: This runs ALTER TABLE on a hot, large table. Schedule the deploy of this
 * migration during a quiet window. With pt-online-schema-change or MySQL 8 online
 * DDL it's online, but still I/O heavy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_data', function (Blueprint $table) {
            $table->index(['vend_code', 'created_at'], 'vend_data_vend_code_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('vend_data', function (Blueprint $table) {
            $table->dropIndex('vend_data_vend_code_created_at_index');
        });
    }
};
