<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setting/Edit "View Screen" history (Brian, 2026-10-15: "save the last five pictures"). Each
 * delivered capture is kept privately on the default disk (DO Spaces in prod); VendScreenshotHistory
 * prunes a machine to its newest five (row + file) on every save. A screen can show a customer's
 * QR payment or member details, so the files are private and served only through the gated route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vend_screenshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id');
            // Who clicked View Screen (null if the click came from outside a web session).
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('path');
            $table->string('mime', 32);
            $table->unsignedInteger('bytes');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['vend_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vend_screenshots');
    }
};
