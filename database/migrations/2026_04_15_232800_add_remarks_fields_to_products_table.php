<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('remarks_updated_by')->nullable()->after('remarks');
            $table->timestamp('remarks_updated_at')->nullable()->after('remarks_updated_by');

            $table->foreign('remarks_updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['remarks_updated_by']);
            $table->dropColumn('remarks_updated_by');
            $table->dropColumn('remarks_updated_at');
        });
    }
};
