<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->unsignedTinyInteger('controller_silent_level')->default(0);
            $table->timestamp('controller_silent_alert_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn(['controller_silent_level', 'controller_silent_alert_at']);
        });
    }
};
