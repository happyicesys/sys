<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_payout_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();   // BATCH-000045
            $table->string('method')->default('paynow');
            $table->bigInteger('created_by')->nullable();
            $table->string('csv_path')->nullable();
            $table->integer('count')->default(0);
            $table->integer('total_cents')->default(0);
            $table->string('status')->default('generated'); // generated | uploaded
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_payout_batches');
    }
};
