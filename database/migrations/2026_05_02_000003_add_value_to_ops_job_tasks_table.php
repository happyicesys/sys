<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_tasks', function (Blueprint $table) {
            // value stored in cents, consistent with ops_job_items
            $table->bigInteger('value')->default(0)->after('cash_collected');
        });
    }

    public function down(): void
    {
        Schema::table('ops_job_tasks', function (Blueprint $table) {
            $table->dropColumn('value');
        });
    }
};
