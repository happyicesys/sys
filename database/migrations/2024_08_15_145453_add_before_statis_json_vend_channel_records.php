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
            $table->json('before_statis_json')->nullable()->after('before_label');
            $table->json('after_statis_json')->nullable()->after('after_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_channel_records', function (Blueprint $table) {
            $table->dropColumn('before_statis_json');
            $table->dropColumn('after_statis_json');
        });
    }
};
