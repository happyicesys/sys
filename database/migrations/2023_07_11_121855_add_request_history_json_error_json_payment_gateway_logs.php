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
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->json('request_history_json')->nullable()->after('response');
            $table->json('error_json')->nullable()->after('history_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->dropColumn('request_history_json');
            $table->dropColumn('error_json');
        });
    }
};
