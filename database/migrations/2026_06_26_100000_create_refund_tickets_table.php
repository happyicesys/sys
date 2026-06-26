<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();           // RFD-000123

            // machine / ownership
            $table->string('vend_code')->index();            // = vends.code = machineID
            $table->bigInteger('vend_id')->nullable()->index();
            $table->bigInteger('operator_id')->nullable()->index();

            // matched source (null for manual-review tickets)
            $table->bigInteger('vend_transaction_id')->nullable()->index();
            $table->bigInteger('payment_gateway_log_id')->nullable()->index();

            // customer input
            $table->string('reason_code')->nullable();
            $table->text('reason_text')->nullable();
            $table->string('refund_method')->nullable();     // paynow | paypal | nayax_auto | none
            $table->string('payout_destination')->nullable();// PayNow no / NRIC / UEN / PayPal email
            $table->json('payout_meta_json')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            // derived amount (sum of selected items; NOT entered by customer)
            $table->integer('claimed_amount_cents')->default(0);

            // manual-review path
            $table->boolean('is_manual')->default(false);
            $table->string('entered_day')->nullable();        // today | yesterday (or actual date for manual)
            $table->integer('entered_amount_cents')->nullable();
            $table->string('approx_time')->nullable();

            // payment channel classification
            $table->string('payment_channel')->nullable();    // qr | nayax | other_pos | unknown
            $table->boolean('is_auto_refund_channel')->default(false); // Nayax -> external auto refund

            // system validation
            $table->string('system_recommendation')->nullable(); // proceed | review | reject
            $table->json('system_validation_json')->nullable();
            $table->boolean('auto_refund_detected')->default(false);

            // workflow
            $table->string('status')->default('submitted')->index();
            $table->bigInteger('ops_verified_by')->nullable();
            $table->timestamp('ops_verified_at')->nullable();
            $table->text('ops_remarks')->nullable();
            $table->bigInteger('submitted_for_approval_by')->nullable();
            $table->timestamp('submitted_for_approval_at')->nullable();
            $table->bigInteger('manager_approved_by')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->bigInteger('payout_batch_id')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // email
            $table->string('last_email_template')->nullable();
            $table->timestamp('last_email_sent_at')->nullable();

            // anti-abuse
            $table->string('submit_ip')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['vend_code', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_tickets');
    }
};
