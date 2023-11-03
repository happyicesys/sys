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
            $table->datetime('qty_sold_at')->nullable();
            $table->datetime('qty_restocked_at')->nullable();
            $table->string('qty_not_available_duration')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_channels', function (Blueprint $table) {
            $table->dropColumn('qty_sold_at');
            $table->dropColumn('qty_restocked_at');
            $table->dropColumn('qty_not_available_duration');
        });
    }
};
