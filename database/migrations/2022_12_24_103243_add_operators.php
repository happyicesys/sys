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
        Schema::table('operators', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->bigInteger('country_id');
            $table->bigInteger('created_by');
            $table->datetime('deactivated_at')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->bigInteger('profile_id')->nullable();
            $table->text('remarks')->nullable();
            $table->string('timezone')->default('Asia/Singapore');
            $table->bigInteger('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropColumn('code');
            $table->dropColumn('country_id');
            $table->dropColumn('created_by');
            $table->dropColumn('deactivated_at');
            $table->dropColumn('name');
            $table->dropColumn('is_active');
            $table->dropColumn('profile_id');
            $table->dropColumn('remarks');
            $table->dropColumn('timezone');
            $table->dropColumn('updated_by');
        });
    }
};
