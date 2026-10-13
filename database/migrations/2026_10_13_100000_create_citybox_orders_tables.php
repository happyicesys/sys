<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CityBox chiller orders, mirrored from their `order_list` (pull, one call per day; 2026-10-13).
 *
 * A raw mirror, upserted on THEIR order id, because a past day keeps changing after the fact:
 * a pay-later order is captured up to 14 days after it was taken (order 68: taken 09-16, paid
 * 09-30) and a refund moves status 1 → 3 → 4. Each row is projected into vend_transactions
 * (CityboxOrderProjector) and deducts the chiller's stock once (ChillerStockLedger).
 * `payload_hash` lets a re-pull skip every order that did not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citybox_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('citybox_order_id')->unique();   // their `id`
            $table->string('order_name', 64)->index();                  // their order number, = vend_transactions.order_id
            $table->string('citybox_equipment_id', 64);
            $table->unsignedBigInteger('vend_id')->nullable();
            $table->smallInteger('order_status');                       // 0 unpaid, 1 paid, 2 paying, 3 refund applied, 4 refunded, 5 rejected, -1 cancelled
            $table->unsignedInteger('qty')->default(0);
            $table->integer('money_cents')->default(0);                 // paid
            $table->integer('good_money_cents')->default(0);            // goods value before discount
            $table->string('refer', 32)->nullable();                    // payment channel raw (icb_antom)
            $table->smallInteger('refer_type')->nullable();
            $table->string('pay_label', 32)->nullable();                // their `transaction`: CARD / APPLEPAY / TNG
            $table->timestamp('ordered_at')->nullable();                // goods taken
            $table->timestamp('paid_at')->nullable();                   // money captured
            $table->char('payload_hash', 32);
            $table->json('raw_json');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('status_changed_at')->nullable();
            // Stock: deducted once, or why not (before the ledger baseline, no vend, …).
            // Units taken off our ledger per mark1 product id ({"971":1}). Kept so a later change to
            // the order (an AI correction, a cancel) moves stock by the DIFFERENCE, never twice.
            $table->json('stock_deducted_json')->nullable();
            $table->timestamp('stock_deducted_at')->nullable();
            $table->string('stock_note', 64)->nullable();
            // Sale: the vend_transactions row, or why there is none yet.
            $table->unsignedBigInteger('vend_transaction_id')->nullable()->index();
            $table->timestamp('projected_at')->nullable();
            $table->string('projection_error', 255)->nullable();
            $table->timestamps();

            $table->index(['vend_id', 'ordered_at']);
            $table->index('ordered_at');
        });

        Schema::create('citybox_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();            // citybox_orders.id
            $table->unsignedBigInteger('citybox_product_id');
            $table->unsignedBigInteger('product_id')->nullable();       // resolved via citybox_products
            $table->string('product_name')->nullable();
            $table->integer('price_cents')->default(0);
            $table->unsignedInteger('qty')->default(0);
            $table->integer('total_cents')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citybox_order_items');
        Schema::dropIfExists('citybox_orders');
    }
};
