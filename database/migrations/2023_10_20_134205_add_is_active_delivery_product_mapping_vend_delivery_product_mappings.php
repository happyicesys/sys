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
        Schema::table('delivery_product_mappings', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('remarks')->index();
            // $table->dropColumn('status');
        });
        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('vend_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_product_mappings', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
