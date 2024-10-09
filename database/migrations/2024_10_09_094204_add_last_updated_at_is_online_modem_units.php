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
        Schema::table('modem_units', function (Blueprint $table) {
            $table->datetime('last_updated_at')->nullable();
            $table->boolean('is_online')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modem_units', function (Blueprint $table) {
            $table->dropColumn('last_updated_at');
            $table->dropColumn('is_online');
        });
    }
};
