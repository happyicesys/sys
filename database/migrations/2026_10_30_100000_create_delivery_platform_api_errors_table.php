<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every Grab exchange that failed, both ways (Brian, 2026-10-30): our calls Grab
 * refused or never answered, Grab's callbacks we answered with an error, and orders
 * Grab has that never reached us. Kept 30 days (grab:prune-api-errors).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_platform_api_errors', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 16); // outgoing | incoming
            $table->string('action', 48);
            $table->string('http_method', 8)->nullable();
            $table->string('path')->nullable();
            $table->unsignedSmallInteger('status')->nullable(); // null = no answer
            $table->string('merchant_id')->nullable();
            $table->string('order_id')->nullable();
            $table->unsignedBigInteger('delivery_platform_ref_number_id')->nullable();
            $table->unsignedBigInteger('delivery_platform_operator_id')->nullable();
            $table->text('message')->nullable();
            $table->json('request_json')->nullable();
            $table->json('response_json')->nullable();
            $table->timestamp('created_at')->nullable()->index('dp_api_errors_created_index');
            $table->index(['delivery_platform_ref_number_id', 'created_at'], 'dp_api_errors_ref_created_index');
            $table->index(['merchant_id', 'created_at'], 'dp_api_errors_merchant_created_index');
            $table->index('order_id', 'dp_api_errors_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_api_errors');
    }
};
