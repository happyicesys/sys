<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `vend_transactions`
            ADD COLUMN `effective_transaction_datetime` DATETIME
                GENERATED ALWAYS AS (COALESCE(`transaction_datetime`, `created_at`)) STORED
                AFTER `transaction_datetime`,
            ADD INDEX `vend_transactions_effective_transaction_datetime_index` (`effective_transaction_datetime`)
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `vend_transactions`
            DROP INDEX `vend_transactions_effective_transaction_datetime_index`,
            DROP COLUMN `effective_transaction_datetime`
        SQL);
    }
};
