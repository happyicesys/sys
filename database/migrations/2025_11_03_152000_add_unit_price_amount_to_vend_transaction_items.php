<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `vend_transaction_items`
            ADD COLUMN `unit_price_amount` INT NULL AFTER `unit_cost`,
            ADD INDEX `vend_transaction_items_unit_price_amount_index` (`unit_price_amount`)
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `vend_transaction_items`
            DROP INDEX `vend_transaction_items_unit_price_amount_index`,
            DROP COLUMN `unit_price_amount`
        SQL);
    }
};
