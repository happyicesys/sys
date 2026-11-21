<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grab Menu Sync (Brian, 2026-11-21: "one tab which will record every action and whether it is fully synced …
 * the details on each of the machines"). One `grab_menu_syncs` row per change that reaches Grab (a price rule,
 * a typed price, a store switched, the nightly sync, the system's own check), one `grab_menu_sync_stores` row per
 * store it reached, following that store from "waiting" to Grab's own SUCCESS. Written only by
 * App\Services\Grab\Menu\GrabMenuSyncTracker; pruned after 90 days (grab:prune-menu-syncs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grab_menu_syncs', function (Blueprint $table) {
            $table->id();
            // The operator whose prices changed; NULL = several (nightly sync, the system's check).
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            // NULL = the system.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('kind', 32);
            $table->string('summary', 500);
            $table->json('details')->nullable();
            $table->unsignedInteger('store_count')->default(0);
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('grab_menu_sync_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grab_menu_sync_id')->constrained('grab_menu_syncs')->cascadeOnDelete();
            $table->unsignedBigInteger('delivery_product_mapping_vend_id');
            $table->unsignedBigInteger('delivery_platform_ref_number_id')->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('vend_code', 32)->nullable();
            // waiting | sent | pulled | turned_away | synced | failed | skipped
            $table->string('status', 16);
            // Times Grab was asked for this change (a turned-away ask counts).
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('retry_at')->nullable();
            // Grab's last answer to the ask, or why nothing was asked.
            $table->unsignedSmallInteger('last_code')->nullable();
            $table->string('last_message', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('pulled_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            // The system's own check last found Grab's prices equal to the rule's.
            $table->timestamp('last_checked_at')->nullable();
            // {item id: price in cents} of the menu sent for this change.
            $table->json('expected_json')->nullable();
            $table->timestamps();

            $table->unique(['grab_menu_sync_id', 'delivery_product_mapping_vend_id'], 'grab_menu_sync_stores_sync_binding_unique');
            $table->index(['delivery_product_mapping_vend_id', 'status'], 'grab_menu_sync_stores_binding_status_index');
            $table->index(['merchant_id', 'status'], 'grab_menu_sync_stores_merchant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grab_menu_sync_stores');
        Schema::dropIfExists('grab_menu_syncs');
    }
};
