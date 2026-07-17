<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlement_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commission_settlement_id')->index();
            $table->string('format')->default('cimb_txt');
            $table->string('file_path');
            $table->integer('count')->default(0);
            $table->integer('total_cents')->default(0);
            $table->bigInteger('exported_by')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settlement_exports');
    }
};
