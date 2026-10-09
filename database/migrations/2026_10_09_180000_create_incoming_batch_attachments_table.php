<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files (supplier invoice, DO, photo) kept with an Incoming Stock batch. A batch has
 * no table of its own — it is the product_movements rows sharing a batch_number — so
 * the attachment is keyed by that same string, exactly as Incoming Stock History groups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_batch_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->index();
            $table->string('local_url');
            $table->string('full_url', 1024);
            $table->string('name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_batch_attachments');
    }
};
