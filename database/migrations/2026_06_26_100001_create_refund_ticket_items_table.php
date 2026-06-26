<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('refund_ticket_id')->index();

            // source line item
            $table->bigInteger('vend_transaction_item_id')->nullable()->index();
            $table->bigInteger('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('vend_channel_code')->nullable();
            $table->integer('unit_price_cents')->default(0);

            // evidence snapshotted at submit time
            $table->boolean('had_channel_error')->default(false);
            $table->string('vend_channel_error_code')->nullable();
            $table->integer('channel_error_weightage')->nullable();

            // verdicts
            $table->string('item_recommendation')->nullable(); // proceed | review | reject
            $table->boolean('approved')->nullable();           // admin per-item decision (null = undecided)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_ticket_items');
    }
};
