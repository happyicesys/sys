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
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->integer('driver_eta_seconds')->nullable()->after('driver_assigned_at');
            $table->datetime('driver_eta_updated_at')->nullable()->after('driver_assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->dropColumn('driver_eta_seconds');
            $table->dropColumn('driver_eta_updated_at');
        });
    }
};
