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
        Schema::table('vend_channels', function (Blueprint $table) {
            $table->index(['vend_id', 'code'], 'idx_vend_id_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_channels', function (Blueprint $table) {
            $table->dropIndex('idx_vend_id_code');
        });
    }
};
