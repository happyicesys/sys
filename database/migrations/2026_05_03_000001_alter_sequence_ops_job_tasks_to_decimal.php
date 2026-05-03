<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_tasks', function (Blueprint $table) {
            // Change sequence from integer to decimal(8,2) to support values like 5.1, 15.2
            $table->decimal('sequence', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ops_job_tasks', function (Blueprint $table) {
            $table->integer('sequence')->nullable()->change();
        });
    }
};
