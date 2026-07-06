<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link operators to a payout group. Nullable: an ungrouped operator resolves its
 * originating account from its own operators.bank_account_no instead (locked
 * decision, refund settlement plan §12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->unsignedBigInteger('payout_group_id')->nullable()->after('id');
            $table->index('payout_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropIndex(['payout_group_id']);
            $table->dropColumn('payout_group_id');
        });
    }
};
