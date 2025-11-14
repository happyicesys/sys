<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'idx_vend_temps_type_created_vend';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->indexExists('vend_temps', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vend_temps', function (Blueprint $table) {
            $table->index(['type', 'created_at', 'vend_id'], self::INDEX_NAME);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->indexExists('vend_temps', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vend_temps', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_NAME);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $connection->getDatabaseName())
            ->where('table_name', $connection->getTablePrefix() . $table)
            ->where('index_name', $name)
            ->exists();
    }
};
