<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cms_transaction_by')->nullable()->after('cms_transaction_id');
            $table->datetime('cms_transaction_at')->nullable()->after('cms_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropColumn('cms_transaction_by');
            $table->dropColumn('cms_transaction_at');
        });
    }
};
