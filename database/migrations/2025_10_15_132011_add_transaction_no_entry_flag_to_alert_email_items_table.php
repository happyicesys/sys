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
            $table->boolean('is_send_transaction_no_entry_notification')
                ->default(false)
                ->after('is_send_power_restored_notification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alert_email_items', function (Blueprint $table) {
            $table->dropColumn('is_send_transaction_no_entry_notification');
        });
    }
};
