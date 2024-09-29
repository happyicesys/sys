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
        Schema::table('modem_units', function (Blueprint $table) {
            $table->unsignedBigInteger('modem_type_id')->nullable()->after('imei')->index();
            $table->dropColumn('modem_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modem_units', function (Blueprint $table) {
            //
        });
    }
};
