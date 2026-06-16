<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Idempotent: the OperatorActiveScope global scope already queries
     * `is_active`, so the column likely exists in production. We only add
     * the columns when they are missing to avoid breaking existing infra.
     */
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            if (!Schema::hasColumn('operators', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('operators', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op: dropping these columns would break the
     * OperatorActiveScope global scope and any deactivated records.
     */
    public function down(): void
    {
        // No-op on purpose to protect existing infra.
    }
};
