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
        Schema::create('alert_email_items', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_send_channel_error_log')->default(false);
            $table->boolean('is_send_offline_notification')->default(false);
            $table->boolean('is_send_power_restored_notification')->default(false);
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->foreign('operator_id')->references('id')->on('operators')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_email_items');
    }
};
