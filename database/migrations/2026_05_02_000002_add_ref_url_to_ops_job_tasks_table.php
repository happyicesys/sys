<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_tasks', function (Blueprint $table) {
            $table->string('ref_url', 2048)->nullable()->after('ops_note');
        });
    }

    public function down(): void
    {
        Schema::table('ops_job_tasks', function (Blueprint $table) {
            $table->dropColumn('ref_url');
        });
    }
};
