<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gp_metrics', function (Blueprint $table) {
            $table->unsignedBigInteger('success_count')->default(0)->after('transaction_count');
            $table->unsignedBigInteger('error_count')->default(0)->after('success_count');
            $table->unsignedBigInteger('error_count_no_4_5')->default(0)->after('error_count');
            $table->unsignedBigInteger('error_count_4_5')->default(0)->after('error_count_no_4_5');
        });
    }

    public function down(): void
    {
        Schema::table('gp_metrics', function (Blueprint $table) {
            $table->dropColumn(['success_count', 'error_count', 'error_count_no_4_5', 'error_count_4_5']);
        });
    }
};
