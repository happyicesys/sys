<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vend_bindings', function (Blueprint $table) {
            $table->boolean('is_rental')->default(false);
            $table->boolean('is_profit_sharing')->default(false);
            $table->boolean('is_profit_sharing_percentage')->default(false);
            $table->boolean('is_both_utility_comm')->default(false);
            $table->integer('product_unit_price')->nullable();
            $table->integer('rental')->nullable();
            $table->integer('profit_sharing')->nullable();
            $table->integer('utilities')->nullable();
            $table->integer('adjustment_rate')->nullable();
            $table->boolean('is_pwp')->default(false);
            $table->integer('pwp_adjustment_rate')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
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
};
