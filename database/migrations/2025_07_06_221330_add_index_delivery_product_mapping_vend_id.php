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
        Schema::table('delivery_product_mapping_vend_channels', function (Blueprint $table) {
            $table->index(
                'delivery_product_mapping_vend_id',
                'delivery_product_mapping_vend_channels_vend_mapping_id_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_product_mapping_vend_channels', function (Blueprint $table) {
            $table->dropIndex('delivery_product_mapping_vend_channels_vend_mapping_id_index');
        });
    }
};
