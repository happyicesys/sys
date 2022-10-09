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
        Schema::table('customers', function (Blueprint $table) {
            $table->bigInteger('category_id')->nullable();
            $table->string('code');
            $table->bigInteger('created_by')->nullable();
            $table->datetime('deactivated_at')->nullable();
            $table->bigInteger('first_transaction_id')->nullable();
            $table->bigInteger('handled_by')->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('ops_note')->nullable();
            $table->bigInteger('parent_id')->nullable();
            $table->bigInteger('payment_method_id')->nullable();
            $table->bigInteger('payment_term_id')->nullable();
            $table->bigInteger('profile_id');
            $table->bigInteger('price_template_id')->nullable();
            $table->text('remarks')->nullable();
            $table->bigInteger('status_id');
            $table->bigInteger('updated_by')->nullable();
            $table->bigInteger('zone_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('category_id');
            $table->dropColumn('code');
            $table->dropColumn('created_by');
            $table->dropColumn('deactivated_at');
            $table->dropColumn('first_transaction_id');
            $table->dropColumn('handled_by');
            $table->dropColumn('name');
            $table->dropColumn('is_active');
            $table->dropColumn('ops_note');
            $table->dropColumn('parent_id');
            $table->dropColumn('payment_method_id');
            $table->dropColumn('payment_term_id');
            $table->dropColumn('profile_id');
            $table->dropColumn('price_template_id');
            $table->dropColumn('remarks');
            $table->dropColumn('status_id');
            $table->dropColumn('updated_by');
            $table->dropColumn('zone_id');
        });
    }
};
