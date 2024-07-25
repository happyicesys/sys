<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->decimal('gst_vat_rate', 5, 2)->change();
        });

        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->decimal('gst_vat_rate', 5, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->integer('gst_vat_rate')->change();
        });

        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->integer('gst_vat_rate')->change();
        });
    }
};
