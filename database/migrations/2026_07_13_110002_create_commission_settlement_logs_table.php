<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlement_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commission_settlement_id')->index();
            $table->bigInteger('actor_id')->nullable();
            $table->string('actor_label')->nullable();
            $table->string('action');
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settlement_logs');
    }
};
