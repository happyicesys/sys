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
        Schema::table('alert_email_items', function (Blueprint $table) {
            if (!Schema::hasColumn('alert_email_items', 'email')) {
                $table->string('email')->after('id');
            }
            if (!Schema::hasColumn('alert_email_items', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('email');
            }
            if (!Schema::hasColumn('alert_email_items', 'is_send_channel_error_log')) {
                $table->boolean('is_send_channel_error_log')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('alert_email_items', 'is_send_offline_notification')) {
                $table->boolean('is_send_offline_notification')->default(false)->after('is_send_channel_error_log');
            }
            if (!Schema::hasColumn('alert_email_items', 'is_send_power_restored_notification')) {
                $table->boolean('is_send_power_restored_notification')->default(false)->after('is_send_offline_notification');
            }
            if (!Schema::hasColumn('alert_email_items', 'operator_id')) {
                $table->unsignedBigInteger('operator_id')->nullable()->after('is_send_power_restored_notification')->index();
                $table->foreign('operator_id')->references('id')->on('operators')->onDelete('cascade');
            }
            if (!Schema::hasColumn('alert_email_items', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('operator_id')->index();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alert_email_items', function (Blueprint $table) {
            if (Schema::hasColumn('alert_email_items', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('alert_email_items', 'operator_id')) {
                $table->dropForeign(['operator_id']);
                $table->dropColumn('operator_id');
            }
            if (Schema::hasColumn('alert_email_items', 'is_send_power_restored_notification')) {
                $table->dropColumn('is_send_power_restored_notification');
            }
            if (Schema::hasColumn('alert_email_items', 'is_send_offline_notification')) {
                $table->dropColumn('is_send_offline_notification');
            }
            if (Schema::hasColumn('alert_email_items', 'is_send_channel_error_log')) {
                $table->dropColumn('is_send_channel_error_log');
            }
            if (Schema::hasColumn('alert_email_items', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('alert_email_items', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
