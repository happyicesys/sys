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
        Schema::table('vend_prefixes', function (Blueprint $table) {
            $table->bigInteger('operator_id')->index()->unsigned()->nullable();
        });
        Schema::table('vend_configs', function (Blueprint $table) {
            $table->bigInteger('operator_id')->index()->unsigned()->nullable();
        });
        Schema::table('simcards', function (Blueprint $table) {
            $table->bigInteger('operator_id')->index()->unsigned()->nullable();
        });
        Schema::table('cashless_terminals', function (Blueprint $table) {
            $table->bigInteger('operator_id')->index()->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_prefixes', function (Blueprint $table) {
            $table->dropColumn('operator_id');
        });
        Schema::table('vend_configs', function (Blueprint $table) {
            $table->dropColumn('operator_id');
        });
        Schema::table('simcards', function (Blueprint $table) {
            $table->dropColumn('operator_id');
        });
        Schema::table('cashless_terminals', function (Blueprint $table) {
            $table->dropColumn('operator_id');
        });
    }
};
