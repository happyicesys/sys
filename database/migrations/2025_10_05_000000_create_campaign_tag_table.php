<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['x', 'y']);
            $table->timestamps();
            $table->unique(['campaign_id', 'tag_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_tag');
    }
};
