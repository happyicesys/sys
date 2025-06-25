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
        Schema::create('hid_card_vend', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('hid_card_id')->unsigned()->index();
            $table->bigInteger('vend_id')->unsigned()->index();
            $table->timestamps();

            $table->unique(['hid_card_id', 'vend_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hid_card_vend');
    }
};
