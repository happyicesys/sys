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
        Schema::create('delivery_platform_ref_numbers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('delivery_platform_id')->nullable()->index();
            $table->string('ref_number');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->bigInteger('customer_id')->nullable()->index();
            $table->bigInteger('delivery_platform_ref_number_id')->nullable()->index('dpmv_dp_ref_num_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_ref_numbers');

        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->dropColumn('delivery_platform_ref_number_id');
        });
    }
};
