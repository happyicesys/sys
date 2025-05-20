<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompositeIndexesToOpsJobItemsTable extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->index(['customer_id', 'status', 'created_at'], 'idx_customer_status_created_desc');
            $table->index(['customer_id', 'status', 'created_at'], 'idx_customer_status_created_asc');
        });
    }

    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropIndex('idx_customer_status_created_desc');
            $table->dropIndex('idx_customer_status_created_asc');
        });
    }
}
