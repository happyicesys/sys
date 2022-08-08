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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('code');
            $table->integer('total_amount')->default(0);
            $table->integer('subtotal_amount')->default(0);
            $table->integer('total_qty')->default(0);
            $table->json('deals_obj')->nullable();
            $table->bigInteger('customer_id');
            $table->datetime('order_date')->nullable();
            $table->datetime('delivery_date')->nullable();
            $table->datetime('payment_date')->nullable();
            $table->string('po_num')->nullable();
            $table->text('inner_remarks')->nullable();
            $table->text('remarks')->nullable();
            $table->bigInteger('payment_method_id')->nullable();
            $table->bigInteger('delivered_by')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->bigInteger('handled_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
