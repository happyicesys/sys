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
        Schema::table('vend_bindings', function (Blueprint $table) {
            $table->dropColumn('is_rental');
            $table->dropColumn('is_profit_sharing');
            $table->dropColumn('is_profit_sharing_percentage');
            $table->dropColumn('is_both_utility_comm');
            $table->dropColumn('product_unit_price');
            $table->dropColumn('rental');
            $table->dropColumn('profit_sharing');
            $table->dropColumn('utilities');
            $table->dropColumn('adjustment_rate');
            $table->dropColumn('is_pwp');
            $table->dropColumn('pwp_adjustment_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_bindings', function (Blueprint $table) {
            //
        });
    }
};
