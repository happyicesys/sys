<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->integer('refillable_amount')->unsigned()->nullable();
            $table->integer('refillable_count')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropColumn('refillable_amount');
            $table->dropColumn('refillable_count');
        });
    }
};
