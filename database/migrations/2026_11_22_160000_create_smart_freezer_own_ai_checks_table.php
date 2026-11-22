<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person's check of HappyIce AI's count on one door session (Brian, 2026-11-22: "a button at Happy ice AI ... mark it as
 * accurate or ... tell the answer straight over there, so every 3 AM you can train based on the truth"). One row per
 * session; a second check replaces the first. `items` = product id => units that really left the cabinet (the truth),
 * `ai_items` = what HappyIce AI said when it was checked. Read by the office Macs' read-only export as the session's truth.
 * Shadow only: nothing here charges, refunds or moves stock (Adjust Qty does that).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_freezer_own_ai_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('smart_freezer_recognition_id')->unique();
            $table->unsignedBigInteger('vend_id')->nullable()->index();
            $table->string('outcome', 16);                 // right | wrong
            $table->json('items');
            $table->json('ai_items')->nullable();
            $table->string('ai_worker', 32)->nullable();
            $table->string('ai_model', 64)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_freezer_own_ai_checks');
    }
};
