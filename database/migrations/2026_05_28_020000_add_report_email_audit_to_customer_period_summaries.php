<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit columns for the "Email Performance Report" action on a locked summary
 * row.
 *
 * The Vue side fires a `mailto:` (so the operator's own mail client composes
 * the message); the server only records WHEN and BY WHOM the button was
 * clicked, so the team has a paper trail of which months were sent out and by
 * which user. Nullable — never sent stays NULL.
 *
 *   report_emailed_at  : last click timestamp.
 *   report_emailed_by  : FK → users (who clicked); nullOnDelete so audit rows
 *                        survive a user being deleted.
 *
 * Mirrors the existing notes_updated_at / notes_updated_by audit pair on the
 * customers table for consistency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'report_emailed_at')) {
                $table->timestamp('report_emailed_at')->nullable()->after('locked_by');
            }
            if (!Schema::hasColumn('customer_period_summaries', 'report_emailed_by')) {
                $table->foreignId('report_emailed_by')
                    ->nullable()
                    ->after('report_emailed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('customer_period_summaries', 'report_emailed_by')) {
                $table->dropConstrainedForeignId('report_emailed_by');
            }
            if (Schema::hasColumn('customer_period_summaries', 'report_emailed_at')) {
                $table->dropColumn('report_emailed_at');
            }
        });
    }
};
