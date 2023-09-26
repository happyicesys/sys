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
            $table->json('category_json')->nullable();
        });

        Schema::table('delivery_product_mapping_items', function (Blueprint $table) {
            $table->json('sub_category_json')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_product_mappings', function (Blueprint $table) {
            $table->dropColumn('category_json');
        });

        Schema::table('delivery_product_mapping_items', function (Blueprint $table) {
            $table->dropColumn('sub_category_json');
        });
    }
};
