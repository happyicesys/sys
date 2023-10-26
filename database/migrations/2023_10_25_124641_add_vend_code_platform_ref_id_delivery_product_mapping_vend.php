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
            $table->string('platform_ref_id')->nullable()->after('vend_id')->index();
            $table->integer('vend_code')->nullable()->after('vend_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->dropColumn('platform_ref_id');
            $table->dropColumn('vend_code');
        });
    }
};
