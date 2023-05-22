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
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->integer('error_code_normalized')->virtualAs('json_unquote(vend_transaction_json->"$.SErr")')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('error_code_normalized');
        });
    }
};
