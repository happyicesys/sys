<?php

use App\Models\Vend;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CityBox chiller channel codes move from <layer><position> (11…59, max 9 SKUs
 * per layer) to <layer><position 2 digits> (101…599, max 99). Rewrites the
 * codes already on disk for smart_chiller vends so the next poll updates the
 * same rows instead of creating a second set:
 *   vend_channels.code                    (unique per vend — 2→3 digits never collides)
 *   product_mapping_items.channel_code    (mirror mappings, machine_type smart_chiller)
 *   ops_job_item_channels.vend_channel_code (denormalised; the FK is vend_channel_id)
 * Only 2-digit codes 10–69 are touched; layer = tens digit, position = units.
 */
return new class extends Migration
{
    private const REMAP = 'FLOOR(%1$s / 10) * 100 + (%1$s %% 10)';

    public function up(): void
    {
        $chillerIds = Vend::withoutGlobalScopes()->where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)->pluck('id');
        if ($chillerIds->isEmpty()) {
            return;
        }

        $itemIds = DB::table('ops_job_items')->whereIn('vend_id', $chillerIds)->pluck('id');

        DB::transaction(function () use ($chillerIds, $itemIds) {
            DB::table('vend_channels')->whereIn('vend_id', $chillerIds)->whereBetween('code', [10, 69])
                ->update(['code' => DB::raw(sprintf(self::REMAP, 'code'))]);

            DB::table('ops_job_item_channels')->whereIn('ops_job_item_id', $itemIds)->whereBetween('vend_channel_code', [10, 69])
                ->update(['vend_channel_code' => DB::raw(sprintf(self::REMAP, 'vend_channel_code'))]);

            $mirrorIds = DB::table('product_mappings')->where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)->pluck('id');
            if ($mirrorIds->isNotEmpty()) {
                DB::table('product_mapping_items')->whereIn('product_mapping_id', $mirrorIds)
                    ->whereRaw("channel_code REGEXP '^[1-6][0-9]$'")
                    ->update([
                        'channel_code' => DB::raw(sprintf('CAST(%s AS CHAR)', sprintf(self::REMAP, 'CAST(channel_code AS UNSIGNED)'))),
                        'sequence' => DB::raw(sprintf(self::REMAP, 'CAST(channel_code AS UNSIGNED)')),
                    ]);
            }
        });
    }

    public function down(): void
    {
        $chillerIds = Vend::withoutGlobalScopes()->where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)->pluck('id');
        if ($chillerIds->isEmpty()) {
            return;
        }
        $back = 'FLOOR(%1$s / 100) * 10 + (%1$s %% 100)';
        $itemIds = DB::table('ops_job_items')->whereIn('vend_id', $chillerIds)->pluck('id');

        DB::transaction(function () use ($chillerIds, $itemIds, $back) {
            DB::table('vend_channels')->whereIn('vend_id', $chillerIds)->whereBetween('code', [101, 699])->where(DB::raw('code % 100'), '<=', 9)
                ->update(['code' => DB::raw(sprintf($back, 'code'))]);
            DB::table('ops_job_item_channels')->whereIn('ops_job_item_id', $itemIds)->whereBetween('vend_channel_code', [101, 699])->where(DB::raw('vend_channel_code % 100'), '<=', 9)
                ->update(['vend_channel_code' => DB::raw(sprintf($back, 'vend_channel_code'))]);
            $mirrorIds = DB::table('product_mappings')->where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)->pluck('id');
            if ($mirrorIds->isNotEmpty()) {
                DB::table('product_mapping_items')->whereIn('product_mapping_id', $mirrorIds)
                    ->whereRaw("channel_code REGEXP '^[1-6]0[1-9]$'")
                    ->update([
                        'channel_code' => DB::raw(sprintf('CAST(%s AS CHAR)', sprintf($back, 'CAST(channel_code AS UNSIGNED)'))),
                        'sequence' => DB::raw(sprintf($back, 'CAST(channel_code AS UNSIGNED)')),
                    ]);
            }
        });
    }
};
