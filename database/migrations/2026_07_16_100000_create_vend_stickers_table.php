<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machine Sticker types — a small controlled vocabulary managed under
 * Data Management, bindable (optional, many-to-many) to a Vend on the
 * machine edit screen and shown on the Serial Number index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vend_stickers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('desc')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vend_stickers');
    }
};
