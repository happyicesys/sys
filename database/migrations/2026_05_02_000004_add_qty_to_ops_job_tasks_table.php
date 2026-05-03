<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_tasks', function (Blueprint $table) {
            $table->integer('qty')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('ops_job_tasks', function (Blueprint $table) {
            $table->dropColumn('qty');
        });
    }
};
