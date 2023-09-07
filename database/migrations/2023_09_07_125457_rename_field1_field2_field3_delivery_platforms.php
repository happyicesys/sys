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
        Schema::table('delivery_platforms', function (Blueprint $table) {
            $table->renameColumn('field1', 'field1_name');
            $table->renameColumn('field2', 'field2_name');
            $table->renameColumn('field3', 'field3_name');
        });

        Schema::table('delivery_platform_operators', function (Blueprint $table) {
            $table->renameColumn('input1', 'field1');
            $table->renameColumn('input2', 'field2');
            $table->renameColumn('input3', 'field3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platforms', function (Blueprint $table) {
            //
        });
    }
};
