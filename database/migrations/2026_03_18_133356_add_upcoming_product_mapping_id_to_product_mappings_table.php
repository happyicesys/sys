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
        Schema::table('product_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('upcoming_product_mapping_id')->nullable()->after('name');
            $table->foreign('upcoming_product_mapping_id')->references('id')->on('product_mappings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_mappings', function (Blueprint $table) {
            $table->dropForeign(['upcoming_product_mapping_id']);
            $table->dropColumn('upcoming_product_mapping_id');
        });
    }
};
