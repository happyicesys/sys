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
            $table->json('last_menu_json')->nullable()->after('delivery_product_mapping_vend_channels_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->dropColumn('last_menu_json');
        });
    }
};
