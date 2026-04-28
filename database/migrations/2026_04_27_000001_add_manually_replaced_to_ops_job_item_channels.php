<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_item_channels', function (Blueprint $table) {
            // Marks a channel that is being manually replaced by a newly-added channel
            $table->boolean('is_manually_replaced')->default(false)->after('is_upcoming_product');
            // On the NEW channel: stores the id of the channel it replaces (for ordering / pairing)
            $table->unsignedBigInteger('replaces_ops_job_item_channel_id')->nullable()->after('is_manually_replaced');
        });
    }

    public function down(): void
    {
        Schema::table('ops_job_item_channels', function (Blueprint $table) {
            $table->dropColumn(['is_manually_replaced', 'replaces_ops_job_item_channel_id']);
        });
    }
};
