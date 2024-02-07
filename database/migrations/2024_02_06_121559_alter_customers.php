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
            $table->dropColumn('bank_id');
            $table->dropColumn('bank_remarks');
            $table->dropColumn('is_freezer');
            $table->dropColumn('ops_note');
            $table->dropColumn('remarks');
            $table->json('customer_json')->nullable();
            $table->string('virtual_customer_prefix')->virtualAs('json_unquote(json_extract(customer_json,"$.prefix"))')->index();
            $table->integer('virtual_customer_code')->virtualAs('json_unquote(json_extract(customer_json,"$.code"))')->index();
            $table->bigInteger('person_id')->nullable()->index()->change();

        });

        Schema::dropIfExists('banks');
        Schema::dropIfExists('payment_terms');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('virtual_customer_prefix');
            $table->dropColumn('virtual_customer_code');
            $table->dropColumn('customer_json');
        });
    }
};
