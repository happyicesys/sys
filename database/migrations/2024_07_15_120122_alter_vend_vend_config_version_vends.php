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
            $table->string('vend_vend_config_version')->nullable()->default('-')->change();
        });

        Schema::table('vend_configs', function (Blueprint $table) {
            $table->string('version')->nullable()->default('-')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            //
        });
    }
};
