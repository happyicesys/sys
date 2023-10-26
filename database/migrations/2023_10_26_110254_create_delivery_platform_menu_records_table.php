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
        Schema::create('delivery_platform_menu_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_platform_operator_id');
            $table->string('delivery_platform_slug')->nullable();
            $table->json('menu_json')->nullable();
            $table->string('platform_ref_id');
            $table->datetime('request_datetime');
            $table->text('remarks')->nullable();
            $table->tinyInteger('type')->default(1)->comment('1: Active, 2: Passive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_menu_records');
    }
};
