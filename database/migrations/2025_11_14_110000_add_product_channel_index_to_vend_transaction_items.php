<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'idx_vti_product_channel';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vend_transaction_items', function (Blueprint $table) {
            if (! $this->indexExists('vend_transaction_items', self::INDEX_NAME)) {
                $table->index(['product_id', 'vend_channel_id'], self::INDEX_NAME);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_transaction_items', function (Blueprint $table) {
            if ($this->indexExists('vend_transaction_items', self::INDEX_NAME)) {
                $table->dropIndex(self::INDEX_NAME);
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $connection->getDatabaseName())
            ->where('table_name', $connection->getTablePrefix() . $table)
            ->where('index_name', $index)
            ->exists();
    }
};
