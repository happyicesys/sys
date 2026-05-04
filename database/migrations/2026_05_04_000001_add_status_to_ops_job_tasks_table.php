<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_tasks', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->after('ops_job_id');
            $table->timestamp('picked_at')->nullable()->after('updated_by');
            $table->unsignedBigInteger('picked_by')->nullable()->after('picked_at');
            $table->timestamp('completed_at')->nullable()->after('picked_by');
            $table->unsignedBigInteger('completed_by')->nullable()->after('completed_at');
            $table->timestamp('undo_picked_at')->nullable()->after('completed_by');
            $table->unsignedBigInteger('undo_picked_by')->nullable()->after('undo_picked_at');
            $table->timestamp('undo_completed_at')->nullable()->after('undo_picked_by');
            $table->unsignedBigInteger('undo_completed_by')->nullable()->after('undo_completed_at');

            $table->foreign('picked_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('undo_picked_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('undo_completed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ops_job_tasks', function (Blueprint $table) {
            $table->dropForeign(['picked_by']);
            $table->dropForeign(['completed_by']);
            $table->dropForeign(['undo_picked_by']);
            $table->dropForeign(['undo_completed_by']);
            $table->dropColumn([
                'status',
                'picked_at', 'picked_by',
                'completed_at', 'completed_by',
                'undo_picked_at', 'undo_picked_by',
                'undo_completed_at', 'undo_completed_by',
            ]);
        });
    }
};
