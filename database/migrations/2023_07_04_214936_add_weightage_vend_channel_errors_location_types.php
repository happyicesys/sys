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
        Schema::table('vend_channel_errors', function (Blueprint $table) {
            $table->integer('weightage')->default(0);
        });

        Schema::table('location_types', function (Blueprint $table) {
            $table->integer('weightage')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_channel_errors', function (Blueprint $table) {
            $table->dropColumn('weightage');
        });

        Schema::table('location_types', function (Blueprint $table) {
            $table->dropColumn('weightage');
        });
    }
};
