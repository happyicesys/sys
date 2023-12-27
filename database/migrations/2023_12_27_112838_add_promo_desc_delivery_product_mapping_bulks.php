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
        Schema::table('delivery_product_mapping_bulks', function (Blueprint $table) {
            $table->string('promo_desc')->nullable()->after('promo_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_product_mapping_bulks', function (Blueprint $table) {
            $table->dropColumn('promo_desc');
        });
    }
};
