<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A smart freezer's daily setpoint plan: at `run_at` (Asia/Singapore, every day) send `setpoint`
 * with `celsius`. `last_run_on` makes each entry fire at most once a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freezer_setpoint_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vend_id')->constrained('vends')->cascadeOnDelete();
            $table->time('run_at');
            $table->smallInteger('celsius');
            $table->boolean('is_active')->default(true);
            $table->date('last_run_on')->nullable();
            $table->foreignId('last_command_id')->nullable()->constrained('freezer_control_commands')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->unique(['vend_id', 'run_at']);
            $table->index(['is_active', 'run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freezer_setpoint_schedules');
    }
};
