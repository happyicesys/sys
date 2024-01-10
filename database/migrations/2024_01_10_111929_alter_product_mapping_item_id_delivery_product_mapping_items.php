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
        Schema::table('delivery_product_mapping_items', function (Blueprint $table) {
            $table->bigInteger('product_mapping_item_id')->unsigned()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_product_mapping_items', function (Blueprint $table) {

        });
    }
};
