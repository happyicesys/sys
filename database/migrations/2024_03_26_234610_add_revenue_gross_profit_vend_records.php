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
        Schema::table('vend_records', function (Blueprint $table) {
            $table->integer('revenue')->default(0);
            $table->integer('gross_profit')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_records', function (Blueprint $table) {
            $table->dropColumn('revenue');
            $table->dropColumn('gross_profit');
        });
    }
};
