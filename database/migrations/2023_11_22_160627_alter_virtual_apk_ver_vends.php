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
        Schema::table('vends', function (Blueprint $table) {
            // $table->dropColumn('virtual_apk_ver');
            $table->integer('virtual_apk_ver')->virtualAs('json_unquote(apk_ver_json->"$.apkver")')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            //
        });
    }
};
