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
        Schema::table('vends', function (Blueprint $table) {
            if (!Schema::hasColumn('vends', 'offline_notification_level')) {
                $table->unsignedTinyInteger('offline_notification_level')->default(0)->after('is_offline_notification_sent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            if (Schema::hasColumn('vends', 'offline_notification_level')) {
                $table->dropColumn('offline_notification_level');
            }
        });
    }
};
