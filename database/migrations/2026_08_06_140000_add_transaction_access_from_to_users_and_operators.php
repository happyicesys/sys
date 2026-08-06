<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Transaction Access From" — the earliest sales date a viewer may see.
 *
 * NULL on both tables = unrestricted, which is every existing row, so this
 * migration is a no-op for current behaviour. That is deliberate: the feature
 * has to ship dark and only start restricting when an admin actually types a
 * date into the box.
 *
 * DATE, not DATETIME. The rollup tables this has to filter (vend_records.date,
 * vend_product_records.date, gp_metrics.txn_date) store a bare date, so a time
 * component could never be honoured there and would leave the same user with a
 * different cut-off depending on which page they were looking at.
 *
 * No index. The column is read once per request (memoised in
 * App\Support\TransactionAccess) to build a predicate against OTHER tables; it
 * is never itself a filter target. users is 70 rows and operators fewer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('transaction_access_from')
                ->nullable()
                ->after('product_access_mode')
                ->comment('Earliest sales date this user may see. NULL = all history.');
        });

        Schema::table('operators', function (Blueprint $table) {
            $table->date('transaction_access_from')
                ->nullable()
                ->after('product_access_mode')
                ->comment('Floor for every user in this operator. Users may set a LATER date, never earlier. NULL = all history.');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('transaction_access_from');
        });

        Schema::table('operators', function (Blueprint $table) {
            $table->dropColumn('transaction_access_from');
        });
    }
};
