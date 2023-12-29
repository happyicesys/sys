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
        Schema::table('vend_records', function (Blueprint $table) {
            $table->integer('online_failure_amount')->default(0);
            $table->integer('online_failure_count')->default(0);
            $table->integer('online_success_amount')->default(0);
            $table->integer('online_success_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_records', function (Blueprint $table) {
            $table->dropColumn('online_failure_amount');
            $table->dropColumn('online_failure_count');
            $table->dropColumn('online_success_amount');
            $table->dropColumn('online_success_count');
        });
    }
};
