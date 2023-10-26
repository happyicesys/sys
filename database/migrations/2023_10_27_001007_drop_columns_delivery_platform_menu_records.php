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
        Schema::table('delivery_platform_menu_records', function (Blueprint $table) {
            $table->dropColumn('delivery_platform_operator_id');
            $table->dropColumn('delivery_product_mapping_vend_id');
            $table->dropColumn('request_datetime');
            $table->dropColumn('remarks');
            $table->dropColumn('type');
            $table->dropColumn('platform_ref_json');
            $table->integer('vend_code')->nullable();
            $table->json('request_json')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_menu_records', function (Blueprint $table) {
            //
        });
    }
};
