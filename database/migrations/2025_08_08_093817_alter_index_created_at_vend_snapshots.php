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
        Schema::table('vend_snapshots', function (Blueprint $table) {
            $table->datetime('created_at')->index()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_snapshots', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->datetime('created_at')->change();
        });
    }
};
