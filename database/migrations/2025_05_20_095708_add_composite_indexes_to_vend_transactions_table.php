<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompositeIndexesToVendTransactionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            // Composite index for common filter/sort pattern
            $table->index(['operator_id', 'transaction_datetime'], 'idx_operator_datetime');

            // Optional: If is_multiple is often queried with above
            $table->index(['operator_id', 'transaction_datetime', 'is_multiple'], 'idx_operator_datetime_multiple');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_operator_datetime');
            $table->dropIndex('idx_operator_datetime_multiple');
        });
    }
}

