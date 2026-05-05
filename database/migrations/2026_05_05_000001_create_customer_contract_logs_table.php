<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * customer_contract_logs
 *
 * Append-only history of placement-contract values for each customer.
 *
 * One row per "version" of the contract. effective_from is when the version
 * starts; effective_to is null while the version is current and gets stamped
 * the moment a new version is written.
 *
 * Used today as an audit trail; required later if/when we split summary rows
 * by mid-month contract changes (the "option 1" segmentation the user
 * mentioned for the future).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contract_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable(); // null = currently active

            // Snapshot of the contract fields at this version
            $table->string('contract_commission_type', 8)->nullable();
            $table->decimal('contract_commission_value', 10, 2)->nullable();
            $table->decimal('contract_commission_value2', 10, 2)->nullable();
            $table->decimal('contract_ps_term', 5, 2)->nullable();
            $table->date('contract_until')->nullable();
            $table->boolean('contract_auto_renewal')->default(false);
            $table->unsignedSmallInteger('contract_min_commitment_period')->nullable();
            $table->unsignedSmallInteger('contract_notice_period')->nullable();

            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32)->default('user'); // user | seeder | system

            $table->timestamps();

            $table->index(['customer_id', 'effective_from'], 'ccl_customer_eff_from_idx');
            $table->index(['customer_id', 'effective_to'], 'ccl_customer_eff_to_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contract_logs');
    }
};
