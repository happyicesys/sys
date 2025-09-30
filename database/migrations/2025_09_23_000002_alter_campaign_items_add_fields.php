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
        Schema::table('campaign_items', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->boolean('is_active')->default(true)->after('uuid');
            $table->unsignedTinyInteger('campaign_type')->nullable()->after('promo_type');
            $table->unsignedTinyInteger('action_type')->nullable()->after('campaign_type');
            $table->unsignedInteger('action_value')->nullable()->after('action_type'); // store cents
            $table->unsignedInteger('cart_amount_threshold')->nullable()->after('action_value'); // store cents
            $table->unsignedInteger('free_qty')->default(0)->after('cart_amount_threshold');
            $table->string('selection_strategy')->nullable()->after('free_qty');

            $table->unique('uuid');
            $table->index(['campaign_type', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_items', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['campaign_type', 'action_type']);
            $table->dropColumn([
                'uuid',
                'is_active',
                'campaign_type',
                'action_type',
                'action_value',
                'cart_amount_threshold',
                'free_qty',
                'selection_strategy',
            ]);
        });
    }
};

