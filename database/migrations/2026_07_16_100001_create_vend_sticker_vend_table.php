<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot binding Vends to their Machine Stickers (many-to-many, all optional).
 * Explicitly named `vend_sticker_vend`; both relations reference it by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vend_sticker_vend', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id');
            $table->unsignedBigInteger('vend_sticker_id');
            $table->timestamps();

            $table->unique(['vend_id', 'vend_sticker_id'], 'vend_sticker_vend_unique');
            $table->foreign('vend_id')->references('id')->on('vends')->cascadeOnDelete();
            $table->foreign('vend_sticker_id')->references('id')->on('vend_stickers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vend_sticker_vend');
    }
};
