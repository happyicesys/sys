<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dispense_records', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_vm_receive_dispense_signal')->default(false);
            $table->string('order_id')->index();
            $table->bigInteger('payment_gateway_log_id')->nullable()->index();
            $table->integer('retries')->default(0);
            $table->string('vend_code')->index();
            $table->string('vend_id')->index();
            $table->bigInteger('vend_transaction_id')->nullable()->index();
            $table->timestamp('vend_transaction_time')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispense_records');
    }
};
