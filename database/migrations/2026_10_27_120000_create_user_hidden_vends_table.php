<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The machine level of a product-limited user (Brian, 2026-10-27): User edit
 * lists the machines carrying their products live, each Show (default) or
 * No Show. A row here = No Show. See App\Support\VendAccess.
 *
 * Table only, deployed ahead of the code that reads it (a push serves code
 * before migrations run); the carry-over of bound machines is the next migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_hidden_vends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vend_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'vend_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hidden_vends');
    }
};
