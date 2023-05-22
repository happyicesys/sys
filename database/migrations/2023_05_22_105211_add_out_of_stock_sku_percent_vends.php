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
        Schema::table('vends', function (Blueprint $table) {
            $table->integer('out_of_stock_sku_percent')->virtualAs('json_unquote(vend_channel_totals_json->"$.outOfStockSkuPercent")')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn('out_of_stock_sku_percent');
        });
    }
};
