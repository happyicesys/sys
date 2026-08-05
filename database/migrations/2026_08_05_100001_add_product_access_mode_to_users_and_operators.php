<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit "unrestricted vs restricted" flag for the product allow-list.
 *
 * The machine allow-list encodes "no restriction" as "the pivot happens to be
 * empty" (see OperatorTransactionFilterScope: `if ($vendIds)` simply never
 * adds the whereIn). That conflates two different states, and here it would be
 * a silent privilege escalation: delete the one product a user is restricted
 * to, the FK cascade empties their pivot, and they become unrestricted.
 *
 *   'all'  => sees every product (default, and what every existing row gets)
 *   'list' => sees exactly what is in the pivot, INCLUDING the empty list,
 *             which means "sees nothing"
 */
return new class extends Migration
{
    private const MODE_COLUMN = 'product_access_mode';

    private const TABLES = ['users', 'operators'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string(self::MODE_COLUMN, 8)
                    ->default('all')
                    ->comment('all = unrestricted; list = restricted to the product allow-list (empty list = sees nothing)');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(self::MODE_COLUMN);
            });
        }
    }
};
