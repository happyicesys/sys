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
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->integer('revenue')->nullable();
            $table->integer('gross_profit')->nullable();
            $table->integer('gross_profit_margin')->nullable();
            $table->integer('unit_cost')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('revenue');
            $table->dropColumn('gross_profit');
            $table->dropColumn('gross_profit_margin');
            $table->dropColumn('unit_cost');
        });
    }
};
