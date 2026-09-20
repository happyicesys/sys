<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service Notice: a repair stop inside an ops job (a driver-day), against one
 * machine. A sibling of ops_job_items / ops_job_tasks, never a flag on them —
 * a repair visit touches no stock, cash, freeze or cms sync.
 * Plan: SERVICE_NOTICE_PLAN_2026-09-19.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code');
            $table->unsignedBigInteger('operator_id');
            $table->foreignId('ops_job_id')->constrained('ops_jobs')->cascadeOnDelete();
            $table->unsignedBigInteger('vend_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('sequence', 8, 2)->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->text('remarks')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->dateTime('undo_completed_at')->nullable();
            $table->unsignedBigInteger('undo_completed_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['operator_id', 'code']);
            $table->index(['vend_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('service_notice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_notice_id')->constrained('service_notices')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->unsignedTinyInteger('status')->default(1);
            $table->text('desc');
            $table->text('desc_before')->nullable();
            $table->text('desc_after')->nullable();
            $table->text('incomplete_reason')->nullable();
            $table->dateTime('status_changed_at')->nullable();
            $table->unsignedBigInteger('status_changed_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_notice_items');
        Schema::dropIfExists('service_notices');
    }
};
