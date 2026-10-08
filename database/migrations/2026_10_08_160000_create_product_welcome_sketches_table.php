<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One welcome-screen sketch per product: the drawing the smart freezer's welcome scene drops
 * instead of the catalog photo (ProductWelcomeSketchService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_welcome_sketches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            // pending → generating → ready | failed
            $table->string('status', 16)->default('pending');
            // seed (the approved set), generated (image model), upload (a person)
            $table->string('source', 16)->nullable();
            $table->string('path')->nullable();
            $table->string('url', 1024)->nullable();
            // The photo the sketch was drawn from; a new photo means a new sketch.
            $table->string('source_photo_url', 1024)->nullable();
            $table->string('source_photo_hash', 64)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('reason', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_welcome_sketches');
    }
};
