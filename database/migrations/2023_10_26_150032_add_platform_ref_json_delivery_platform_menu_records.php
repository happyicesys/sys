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
        Schema::table('delivery_platform_menu_records', function (Blueprint $table) {
            $table->json('platform_ref_json')->nullable()->after('platform_ref_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_menu_records', function (Blueprint $table) {
            $table->dropColumn('platform_ref_json');
        });
    }
};
