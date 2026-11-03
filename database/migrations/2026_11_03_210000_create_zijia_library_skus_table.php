<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The newest entries of Zijia's SKU library, kept locally so a product can be matched against what
 * was registered in their portal (ZijiaLibraryMirror). Whether an entry is linked is never stored:
 * it is linked when a product's barcode equals its product_code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zijia_library_skus', function (Blueprint $table) {
            $table->id();
            $table->string('sys_sku_id', 32)->unique();
            $table->string('product_code', 64)->index();
            $table->string('sku_name', 255);
            $table->string('name_key', 255)->index();
            $table->string('spec', 50)->nullable();
            $table->string('brand_name', 100)->nullable();
            $table->timestamp('library_updated_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->unsignedBigInteger('dismissed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zijia_library_skus');
    }
};
