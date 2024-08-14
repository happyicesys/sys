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
        Schema::table('vend_channel_records', function (Blueprint $table) {
            $table->char('before_label')->nullable()->after('before_data_created_at');
            $table->char('after_label')->nullable()->after('after_data_created_at');
            $table->json('stage_data_json')->nullable()->after('customer_id');
            $table->datetime('stage_data_created_at')->nullable()->after('customer_id')->index();
            $table->char('stage_label')->nullable()->after('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_channel_records', function (Blueprint $table) {
            $table->dropColumn('before_label');
            $table->dropColumn('after_label');
            $table->dropColumn('stage_data_json');
            $table->dropColumn('stage_data_created_at');
            $table->dropColumn('stage_label');
        });
    }
};
