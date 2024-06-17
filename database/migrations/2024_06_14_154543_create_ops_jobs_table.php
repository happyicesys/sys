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
        Schema::create('ops_jobs', function (Blueprint $table) {
            $table->id();
            $table->integer('code')->unique();
            $table->bigInteger('created_by')->unsigned();
            $table->datetime('date')->index();
            $table->bigInteger('delivered_by')->unsigned()->nullable();
            $table->bigInteger('operator_id')->unsigned()->index();
            $table->datetime('picked_at')->nullable();
            $table->bigInteger('picked_by')->unsigned()->nullable();
            $table->integer('status')->default(1);
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ops_jobs');
    }
};
