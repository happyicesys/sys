<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vend_smart_alerts', function (Blueprint $table) {
            if (!Schema::hasColumn('vend_smart_alerts', 'is_email_alert_sent')) {
                $table->boolean('is_email_alert_sent')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('vend_smart_alerts', 'email_alert_sent_at')) {
                $table->timestamp('email_alert_sent_at')->nullable()->after('is_email_alert_sent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_smart_alerts', function (Blueprint $table) {
            if (Schema::hasColumn('vend_smart_alerts', 'email_alert_sent_at')) {
                $table->dropColumn('email_alert_sent_at');
            }
            if (Schema::hasColumn('vend_smart_alerts', 'is_email_alert_sent')) {
                $table->dropColumn('is_email_alert_sent');
            }
        });
    }
};
