<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            // Human-readable summary of the items a customer typed in on a manual
            // (unmatched) claim, e.g. "Walls's Ice Ball Lemon (Channel #43) × 2".
            $table->string('manual_items_summary', 1000)->nullable()->after('reason_text');
            // The payment method the customer declared on a manual claim (free
            // text like "PayNow / QR code"). Kept separate from the semantic
            // payment_channel enum so classification/auto-refund logic is untouched.
            $table->string('manual_pay_method', 100)->nullable()->after('manual_items_summary');
        });
    }

    public function down(): void
    {
        Schema::table('refund_tickets', function (Blueprint $table) {
            $table->dropColumn(['manual_items_summary', 'manual_pay_method']);
        });
    }
};
