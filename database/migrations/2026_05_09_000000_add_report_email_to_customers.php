<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "Performance Report email" feature fields to customers.
 *
 * - report_email: dedicated address used by the Customer Summary "Email"
 *   action. Kept separate from the existing contact.email so the report
 *   delivery channel doesn't get coupled to the contact relation (which
 *   may belong to ops, billing, etc).
 *
 * - is_report_email_enabled: opt-in toggle. Defaults to false; the
 *   Customer/Edit page only allows it to be flipped on once a valid
 *   report_email has been entered. The Email button on Customer Summary
 *   ONLY surfaces when this flag is true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('report_email', 191)->nullable()->after('contract_remarks');
            $table->boolean('is_report_email_enabled')->default(false)->after('report_email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['report_email', 'is_report_email_enabled']);
        });
    }
};
