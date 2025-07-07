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
        Schema::table('vend_records', function (Blueprint $table) {
            $table->index(['location_type_id', 'operator_id', 'date', 'vend_id'], 'idx_location_operator_date_vend');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_records', function (Blueprint $table) {
            $table->dropIndex('idx_location_operator_date_vend');
        });
    }
};
