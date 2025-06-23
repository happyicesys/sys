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
        Schema::create('vend_voucher', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('vend_id')->unsigned()->index();
            $table->bigInteger('voucher_id')->unsigned()->index();
            $table->timestamps();

            $table->unique(['vend_id', 'voucher_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_voucher');
    }
};
