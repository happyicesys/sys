<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CityBox chiller sales are paid through CityBox (Antom), never through a rail mark1 holds, so
 * they get their own payment methods (Brian, 2026-10-13: "Citybox - Card"). Reusing Card
 * Terminal (code 1) would pull them into Card Settlement / NETS matching, which keys on that
 * code. No payment_gateway_id: the Txn, Revenue & Settlement tab shows them as an unreconciled
 * rail. Idempotent: a code that already exists is left alone.
 */
return new class extends Migration
{
    private const METHODS = [
        900 => 'Citybox - Other',
        901 => 'Citybox - Card',
        902 => 'Citybox - Apple Pay',
        903 => 'Citybox - TNG',
    ];

    public function up(): void
    {
        $existing = DB::table('payment_methods')->whereIn('code', array_keys(self::METHODS))->pluck('code')->map(fn ($c) => (int) $c)->all();
        foreach (self::METHODS as $code => $name) {
            if (! in_array($code, $existing, true)) {
                DB::table('payment_methods')->insert([
                    'code' => $code, 'name' => $name, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        // A query-builder insert skips OptionCacheBuster's model events: drop the dropdown cache by hand.
        Cache::forget('payment_methods');
    }

    public function down(): void
    {
        DB::table('payment_methods')->whereIn('code', array_keys(self::METHODS))->delete();
    }
};
