<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site (commission / location-fee) settlement — a dated, per-payout-group batch
 * of site payouts, mirroring the refund settlement lifecycle (open → closed).
 * Members are customer_period_summaries rows (via commission_settlement_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();          // CST-260713-HIPL-01
            $table->date('settlement_date');
            $table->unsignedBigInteger('payout_group_id')->nullable();
            $table->unsignedBigInteger('operator_id')->nullable(); // set for ungrouped operators
            $table->unsignedInteger('sequence');
            $table->string('status')->default('open');      // open | closed
            $table->integer('count')->default(0);
            $table->integer('total_cents')->default(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->bigInteger('exported_by')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->string('csv_path')->nullable();
            $table->timestamps();

            $table->unique(
                ['settlement_date', 'payout_group_id', 'operator_id', 'sequence'],
                'cst_settlement_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settlements');
    }
};
