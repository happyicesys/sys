<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welcome-sketch check (2026-11-04): what a model found when it compared a finished drawing with its
 * photo. NULL status = never checked. Advice only: nothing reads it to decide what goes live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_welcome_sketch_versions', function (Blueprint $table) {
            $table->string('check_status', 16)->nullable()->after('last_error');
            $table->json('check_issues')->nullable()->after('check_status');
            $table->string('check_model', 64)->nullable()->after('check_issues');
            $table->text('check_error')->nullable()->after('check_model');
            $table->timestamp('checked_at')->nullable()->after('check_error');
        });
    }

    public function down(): void
    {
        Schema::table('product_welcome_sketch_versions', function (Blueprint $table) {
            $table->dropColumn(['check_status', 'check_issues', 'check_model', 'check_error', 'checked_at']);
        });
    }
};
