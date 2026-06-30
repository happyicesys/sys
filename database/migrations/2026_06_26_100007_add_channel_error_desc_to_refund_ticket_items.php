<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_ticket_items', function (Blueprint $table) {
            $table->string('channel_error_desc')->nullable()->after('vend_channel_error_code');
        });
    }

    public function down(): void
    {
        Schema::table('refund_ticket_items', function (Blueprint $table) {
            $table->dropColumn('channel_error_desc');
        });
    }
};
