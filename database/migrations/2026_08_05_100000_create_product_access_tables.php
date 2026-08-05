<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Access Product(s)" — a per-Operator / per-User product allow-list.
 *
 * Mirrors the existing machine allow-list (user_vend), one dimension over:
 * user_vend answers "which machines can this person see", these answer
 * "which SKUs".
 *
 * Table names follow Laravel's alphabetical belongsToMany convention
 * (product_user, operator_product), same as user_vend / operator_vend.
 * product_user reads backwards but keeps every relation argument-free.
 *
 * NOTE the UNIQUE keys: user_vend has none, which is why its controller has
 * to diff-and-attach one id at a time. With the unique key we can use sync().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id'], 'product_user_unique');
            $table->index('product_id', 'product_user_product_idx');
        });

        Schema::create('operator_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['operator_id', 'product_id'], 'operator_product_unique');
            $table->index('product_id', 'operator_product_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_product');
        Schema::dropIfExists('product_user');
    }
};
