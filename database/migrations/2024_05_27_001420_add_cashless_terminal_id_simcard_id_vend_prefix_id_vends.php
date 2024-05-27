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
            // $table->bigInteger('cashless_terminal_id')->nullable();
            // $table->bigInteger('simcard_id')->nullable();
            $table->bigInteger('vend_prefix_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            // $table->dropColumn('cashless_terminal_id');
            // $table->dropColumn('simcard_id');
            $table->dropColumn('vend_prefix_id');
        });
    }
};
