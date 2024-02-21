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
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('virtual_customer_prefix');
            $table->dropColumn('virtual_customer_code');
            $table->renameColumn('customer_json', 'person_json');
            // $table->string('virtual_customer_prefix')->virtualAs('json_unquote(json_extract(person_json,"$.prefix"))')->index();
            // $table->integer('virtual_customer_code')->virtualAs('json_unquote(json_extract(person_json,"$.code"))')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('person_json', 'customer_json');
        });
    }
};
