<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Freezer AI Training (2026-10-06): a product's modelling application to Zijia's algorithm
 * (算法服务接口文档 §5 `sys.sku.sync.put`), made from Product → Edit, and every exchange about it —
 * what we sent, what they answered, their approval callback (§7) — as an event log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zijia_sku_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('application_no', 40)->unique();
            $table->string('status', 16)->index();
            $table->string('sku_name', 255)->nullable();
            $table->string('brand_name', 100)->nullable();
            $table->string('spec', 50)->nullable();
            $table->unsignedSmallInteger('category')->nullable();
            $table->unsignedSmallInteger('package_type')->nullable();
            $table->string('product_code', 64)->nullable();
            $table->text('package_image_url')->nullable();
            $table->json('model_pics')->nullable();
            $table->string('attach', 50)->nullable();
            $table->text('callback_url')->nullable();
            $table->string('zijia_sku_id', 64)->nullable();
            $table->string('sys_sku_id', 32)->nullable();
            $table->text('decision_msg')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('zijia_sku_application_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zijia_sku_application_id')->index();
            $table->string('event', 40);
            $table->string('level', 10)->default('info');
            $table->json('detail')->nullable();
            $table->string('user_name', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('zijia_sku_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('zijia_sku_application_id')->nullable()->after('product_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('zijia_sku_notifications', function (Blueprint $table) {
            $table->dropIndex(['zijia_sku_application_id']);
            $table->dropColumn('zijia_sku_application_id');
        });
        Schema::dropIfExists('zijia_sku_application_events');
        Schema::dropIfExists('zijia_sku_applications');
    }
};
