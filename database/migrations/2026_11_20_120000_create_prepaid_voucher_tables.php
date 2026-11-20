<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Next-visit vouchers (Brian, 2026-11-20): after a single full-price touchscreen sale, the customer may pay
 * again for a discounted voucher for the same SKU, kept against their mobile number and redeemed on a later
 * visit. Settings per operator in `prepaid_voucher_campaigns`, one row per voucher in `prepaid_vouchers`.
 * The purchase itself is an ordinary sale row in `vend_transactions` (revenue when paid); a redemption is a
 * $0 sale (or the balance paid) linked here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepaid_voucher_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('operator_id')->unique();
            // Offer on/off ("Pause"): vouchers already bought redeem either way.
            $table->boolean('is_active')->default(false);
            $table->boolean('is_redeem_enabled')->default(true);
            // Card (NETS) may pay for the voucher. Off = QR only, without an app update.
            $table->boolean('is_card_enabled')->default(false); // off until bench 2031 proves a voucher card approval leaves no retained credit
            $table->unsignedTinyInteger('discount_pct')->default(10);
            $table->unsignedInteger('price_step_cents')->default(10);
            $table->unsignedSmallInteger('expiry_days')->default(30);
            $table->unsignedSmallInteger('offer_seconds')->default(20);
            $table->json('excluded_product_ids')->nullable();
            $table->json('excluded_vend_ids')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('prepaid_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operator_id')->index();
            $table->unsignedBigInteger('prepaid_voucher_campaign_id')->nullable();
            // E.164 ("+6591234567"). NULL once purged after the retention period.
            $table->string('phone', 20)->nullable()->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedInteger('list_price_cents');
            $table->unsignedInteger('price_paid_cents');
            $table->unsignedTinyInteger('discount_pct');
            $table->string('pay_rail', 8); // qr | card
            $table->unsignedBigInteger('bought_vend_id');
            $table->string('bought_order_id', 64);
            $table->unsignedBigInteger('bought_vend_transaction_id')->nullable();
            $table->unsignedBigInteger('payment_gateway_log_id')->nullable()->index(); // looked up on every Omise refund (voidForRefundedLog)
            // The order whose item was held while the voucher was bought.
            $table->string('first_order_id', 64)->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('expires_at')->index();
            $table->string('status', 16)->default('active')->index(); // active | reserved | redeemed | voided
            $table->unsignedBigInteger('reserved_vend_id')->nullable();
            $table->string('reserved_token', 40)->nullable();
            $table->dateTime('reserved_until')->nullable();
            $table->dateTime('redeemed_at')->nullable();
            $table->unsignedBigInteger('redeemed_vend_id')->nullable();
            $table->string('redeemed_order_id', 64)->nullable();
            $table->unsignedBigInteger('redeemed_vend_transaction_id')->nullable();
            $table->string('redeemed_mode', 8)->nullable(); // sku | value
            $table->unsignedBigInteger('redeemed_product_id')->nullable();
            $table->unsignedInteger('value_used_cents')->nullable();
            $table->unsignedInteger('forfeited_cents')->nullable();
            $table->unsignedInteger('balance_paid_cents')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason')->nullable();
            $table->dateTime('phone_purged_at')->nullable();
            $table->timestamps();

            $table->unique(['bought_vend_id', 'bought_order_id']);
            $table->index(['phone', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepaid_vouchers');
        Schema::dropIfExists('prepaid_voucher_campaigns');
    }
};
