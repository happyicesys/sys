<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_job_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ops_job_id')->constrained('ops_jobs')->cascadeOnDelete();
            $table->integer('sequence')->nullable()->index();
            $table->string('task_name');
            $table->string('address', 500);
            $table->string('postcode', 10);
            $table->text('ops_note')->nullable();
            // Store in cents for consistency with ops_job_items.cash_amount
            $table->bigInteger('cash_collected')->default(0);
            // Geocoded coordinates from postcode (OneMap)
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('ops_job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_job_tasks');
    }
};
