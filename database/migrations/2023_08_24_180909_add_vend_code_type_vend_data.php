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
        Schema::table('vend_data', function (Blueprint $table) {
            $table->string('type')->nullable();
            $table->integer('vend_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_data', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropColumn('vend_code');
        });
    }
};
