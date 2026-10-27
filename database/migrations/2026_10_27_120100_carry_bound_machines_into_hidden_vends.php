<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A product-limited user stops reading `user_vend` (Brian, 2026-10-27: their
 * machine level is now "every machine but the No Show ones", App\Support\VendAccess),
 * so the bound list of the two such users on prod (test1 → 2240, powerpop →
 * 2603 + 2718) is carried over: every machine carrying their products today that
 * was NOT bound becomes No Show, and their `user_vend` rows go — what they see
 * does not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $listUsers = DB::table('users')
            ->where('product_access_mode', 'list')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('user_vend')->whereColumn('user_vend.user_id', 'users.id'))
            ->pluck('id');

        foreach ($listUsers as $userId) {
            $productIds = DB::table('product_user')->where('user_id', $userId)->pluck('product_id');
            $productIds = $productIds->merge(
                DB::table('product_children')->whereIn('parent_product_id', $productIds)->where('is_active', true)->pluck('child_product_id')
            )->unique()->values();

            $bound = DB::table('user_vend')->where('user_id', $userId)->pluck('vend_id');

            $hide = DB::table('vend_channels')
                ->where('is_active', 1)
                ->where('capacity', '>', 0)
                ->whereIn('product_id', $productIds)
                ->whereNotIn('vend_id', $bound)
                ->distinct()
                ->pluck('vend_id');

            DB::table('user_hidden_vends')->insertOrIgnore($hide->map(fn ($vendId) => [
                'user_id' => $userId,
                'vend_id' => $vendId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());

            DB::table('user_vend')->where('user_id', $userId)->delete();
        }
    }

    public function down(): void
    {
        // Data carry-over; nothing to undo safely.
    }
};
