<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per generated file so a settlement can carry BOTH artifacts
 * (PayNow -> CIMB .txt, PayPal -> .xlsx), support re-download of the stored file,
 * and audit who exported what and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_settlement_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('refund_payout_batch_id')->index();
            $table->string('method');   // paynow | paypal
            $table->string('format');   // cimb_txt | xlsx
            $table->string('file_path');
            $table->integer('count')->default(0);
            $table->integer('total_cents')->default(0);
            $table->bigInteger('exported_by')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_settlement_exports');
    }
};
