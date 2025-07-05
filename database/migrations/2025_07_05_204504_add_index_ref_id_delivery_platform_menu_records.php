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
            $table->string('ref_id')->index()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_menu_records', function (Blueprint $table) {
            $table->string('ref_id')->change();
            $table->dropIndex(['ref_id']);
        });
    }
};
