<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promote refund_payout_batches into "Refund Settlement" objects: a dated,
 * per-payout-group pool with an open -> closed -> exported -> done lifecycle.
 *
 * Legacy rows (the old on-the-fly BATCH-xxxxx exports) keep is_settlement = false
 * and null settlement columns, so they are untouched and the unique index below
 * (which treats NULLs as distinct in MySQL) never collides for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_payout_batches', function (Blueprint $table) {
            $table->boolean('is_settlement')->default(false)->after('id');
            $table->date('settlement_date')->nullable()->after('reference');
            $table->unsignedBigInteger('payout_group_id')->nullable()->after('settlement_date');
            $table->unsignedBigInteger('operator_id')->nullable()->after('payout_group_id'); // set for ungrouped operators
            $table->unsignedInteger('sequence')->nullable()->after('operator_id');           // NN within (date, head)
            $table->bigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->bigInteger('exported_by')->nullable();
            $table->timestamp('exported_at')->nullable();

            // One open/sequence slot per (date, payout group | operator).
            $table->unique(
                ['settlement_date', 'payout_group_id', 'operator_id', 'sequence'],
                'rpb_settlement_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('refund_payout_batches', function (Blueprint $table) {
            $table->dropUnique('rpb_settlement_key_unique');
            $table->dropColumn([
                'is_settlement', 'settlement_date', 'payout_group_id', 'operator_id',
                'sequence', 'closed_by', 'closed_at', 'exported_by', 'exported_at',
            ]);
        });
    }
};
