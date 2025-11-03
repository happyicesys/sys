<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `vend_transaction_items`
            ADD INDEX `vend_transaction_items_vend_transaction_error_code_index` (
                `vend_transaction_id`, `vend_channel_error_code`
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `vend_transaction_items`
            DROP INDEX `vend_transaction_items_vend_transaction_error_code_index`
        SQL);
    }
};
