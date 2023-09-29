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
        Schema::table('vend_channels', function (Blueprint $table) {
            $table->integer('amount2')->default(0);
            $table->integer('discount_group')->nullable();
            $table->integer('locked_qty')->default(0);
            $table->integer('sku_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_channels', function (Blueprint $table) {
            $table->dropColumn('amount2');
            $table->dropColumn('discount_group');
            $table->dropColumn('locked_qty');
            $table->dropColumn('sku_code');
        });
    }
};
