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
        Schema::create('vend_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('desc')->nullable();
            $table->timestamps();
        });

        Schema::table('vends', function (Blueprint $table) {
            $table->bigInteger('vend_contract_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_contracts');

        Schema::table('vend_models', function (Blueprint $table) {
            $table->dropColumn('vend_contract_id');
        });
    }
};
