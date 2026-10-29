<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grab Stores (plan §9, Brian 2026-10-29): a Grab store (merchantID) moves between
 * machines over time, and every period is history. A binding row is one period:
 * start_date → end_date to the second, who bound and unbound it and why. Pauses and
 * switch-offs stop overwriting is_active silently: each is a store event.
 *
 * No unique "one open binding per store" index yet: prod still has one store open on
 * two machines (4-C7CDNZDXKEBJPE on 2873 and 2658). The writer enforces it; the index
 * follows once that leftover is closed from the Grab Stores page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->unsignedBigInteger('bound_by')->nullable()->after('start_date');
            $table->unsignedBigInteger('unbound_by')->nullable()->after('end_date');
            $table->string('unbind_reason', 32)->nullable()->after('unbound_by');
            $table->string('unbind_remarks')->nullable()->after('unbind_reason');
            $table->dateTime('paused_until')->nullable()->after('is_active');
            $table->string('pause_reason')->nullable()->after('paused_until');
        });

        Schema::create('delivery_platform_store_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_platform_ref_number_id');
            $table->unsignedBigInteger('delivery_product_mapping_vend_id')->nullable()->index('dp_store_events_binding_index');
            $table->unsignedBigInteger('vend_id')->nullable()->index('dp_store_events_vend_index');
            $table->string('event', 32);
            $table->string('reason')->nullable();
            $table->json('data')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['delivery_platform_ref_number_id', 'created_at'], 'dp_store_events_ref_created_index');
        });

        // A period starts when the row was made; start_date was left empty on 121 of 127.
        DB::table('delivery_product_mapping_vend')
            ->whereNull('start_date')
            ->update(['start_date' => DB::raw('created_at')]);

        // Who, where the audit log has it (from 2026-07-18: one bind, a few pauses).
        // changes = {"field": [old, new]}.
        DB::table('user_logs')
            ->where('auditable_type', 'App\\Models\\DeliveryProductMappingVend')
            ->whereIn('event', ['created', 'updated'])
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get(['auditable_id', 'event', 'user_id', 'changes', 'created_at'])
            ->each(function ($log) {
                $binding = DB::table('delivery_product_mapping_vend')->where('id', $log->auditable_id)->first();
                if (! $binding) {
                    return;
                }
                if ($log->event === 'created') {
                    if (! $binding->bound_by) {
                        DB::table('delivery_product_mapping_vend')->where('id', $binding->id)->update(['bound_by' => $log->user_id]);
                    }

                    return;
                }
                $changes = json_decode($log->changes ?? '', true) ?: [];
                if (array_key_exists('end_date', $changes) && $binding->end_date && ! $binding->unbound_by) {
                    DB::table('delivery_product_mapping_vend')->where('id', $binding->id)->update(['unbound_by' => $log->user_id]);
                }
                if (array_key_exists('is_active', $changes) && $binding->delivery_platform_ref_number_id) {
                    DB::table('delivery_platform_store_events')->insert([
                        'delivery_platform_ref_number_id' => $binding->delivery_platform_ref_number_id,
                        'delivery_product_mapping_vend_id' => $binding->id,
                        'vend_id' => $binding->vend_id,
                        'event' => ($changes['is_active'][1] ?? false) ? 'resumed' : 'paused',
                        'data' => json_encode(['source' => 'user_logs']),
                        'user_id' => $log->user_id,
                        'created_at' => $log->created_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_store_events');

        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->dropColumn(['bound_by', 'unbound_by', 'unbind_reason', 'unbind_remarks', 'paused_until', 'pause_reason']);
        });
    }
};
