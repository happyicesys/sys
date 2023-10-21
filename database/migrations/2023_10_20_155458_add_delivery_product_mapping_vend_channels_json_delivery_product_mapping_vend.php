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
        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->json('delivery_product_mapping_vend_channels_json')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->dropColumn('delivery_product_mapping_vend_channels_json');
        });
    }
};
