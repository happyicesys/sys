<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit UI-02 (2026-10-17): incoming-batch files are supplier invoices / DOs with cost prices.
 * They now carry the batch's operator (OperatorIncomingBatchAttachmentScope keeps other operators
 * out — a batch_number is free text and may repeat across operators) and are stored PRIVATE, so a
 * new row has no public URL. The table was empty on prod when this ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_batch_attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('operator_id')->nullable()->index()->after('batch_number');
            $table->string('full_url', 1024)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('incoming_batch_attachments', function (Blueprint $table) {
            $table->dropColumn('operator_id');
        });
    }
};
