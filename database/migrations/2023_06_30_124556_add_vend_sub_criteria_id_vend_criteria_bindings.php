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
        Schema::table('vend_criteria_bindings', function (Blueprint $table) {
            $table->bigInteger('vend_sub_criteria_id')->unsigned()->nullable()->after('vend_criteria_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_criteria_bindings', function (Blueprint $table) {
            $table->dropColumn('vend_sub_criteria_id');
        });
    }
};
