<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Denormalised refund-request snapshot on each sales transaction, powering the
     * "Refund Request" column on the Transactions page WITHOUT a per-page join to
     * refund_tickets. Kept in sync by RefundTicketObserver (writes on every ticket
     * create / status change / drop / delete) and seeded for history by
     * BackfillVendTransactionRefundRequestSeeder.
     *
     * Holds the LATEST refund ticket matched to the transaction (by
     * vend_transaction_id or order_id). All nullable = no refund request.
     */
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_request_id')->nullable()->after('is_refunded');
            $table->string('refund_request_reference')->nullable()->after('refund_request_id');
            $table->string('refund_request_status')->nullable()->after('refund_request_reference');
            $table->boolean('refund_request_is_dropped')->default(false)->after('refund_request_status');
            $table->index('refund_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropIndex(['refund_request_id']);
            $table->dropColumn([
                'refund_request_id',
                'refund_request_reference',
                'refund_request_status',
                'refund_request_is_dropped',
            ]);
        });
    }
};
