<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the CUSTOMER forced the money back.
 *
 * Omise emits dispute.create / .accept / .close for a chargeback the cardholder
 * raised with their own bank. mark1 acknowledged those events and threw them
 * away, so when the refund itself arrived it was indistinguishable from a
 * refund an admin made on the Omise dashboard — both recorded
 * `omise_external`. Stamping the dispute here lets the refund be attributed to
 * the customer (AutoRefundSource::OMISE_DISPUTE), which is a different fact for
 * ops and for reconciliation.
 *
 * Nullable, no default, no index → INSTANT add.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->dateTime('disputed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->dropColumn('disputed_at');
        });
    }
};
