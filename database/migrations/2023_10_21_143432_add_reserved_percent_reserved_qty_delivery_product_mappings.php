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
        Schema::table('delivery_product_mappings', function (Blueprint $table) {
            $table->integer('reserved_percent')->default(0);
            $table->integer('reserved_qty')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_product_mappings', function (Blueprint $table) {
            $table->dropColumn('reserved_percent');
            $table->dropColumn('reserved_qty');
        });
    }
};
