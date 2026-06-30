<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            $table->string('order_id')->nullable()->index()->after('payment_gateway_log_id');
        });
    }

    public function down(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
    }
};
