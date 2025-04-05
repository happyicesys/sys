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
        Schema::create('voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique(); // redeemable code
            $table->unsignedInteger('member_id')->nullable();
            $table->boolean('is_active')->default(1)->index();
            $table->boolean('is_redeemed')->default(false);
            $table->datetime('redeemed_at')->nullable();
            $table->integer('status')->default(1);
            $table->json('metadata')->nullable(); // store device, location, etc.
            $table->timestamps();
        });

        Schema::table('vouchers', function(Blueprint $table) {
            $table->string('code')->nullable()->index()->change();
            $table->dropColumn('product_id');

            $table->text('desc')->nullable();
            $table->boolean('is_active')->default(1)->index();
            $table->boolean('is_batch_code')->default(0);
            $table->boolean('is_redeemed')->default(0)->index();
            $table->integer('max_promo_value')->nullable();
            $table->unsignedInteger('max_redemption_count')->nullable();
            $table->unsignedInteger('member_id')->nullable();
            $table->integer('min_value')->nullable();
            $table->string('name')->nullable();
            $table->json('product_json')->nullable();
            $table->unsignedInteger('qty')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_items');

        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('code')->change();
            $table->dropColumn('desc');
            $table->dropColumn('is_batch_code');
            $table->dropColumn('max_promo_value');
            $table->dropColumn('max_redemptions');
            $table->dropColumn('min_value');
            $table->dropColumn('name');
            $table->dropColumn('product_json');
            $table->dropColumn('qty');
        });
    }
};
