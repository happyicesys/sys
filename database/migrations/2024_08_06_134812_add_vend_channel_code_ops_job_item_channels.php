<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ops_job_item_channels', function (Blueprint $table) {
            $table->integer('vend_channel_code')->nullable()->after('vend_channel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ops_job_item_channels', function (Blueprint $table) {

        });
    }
};
