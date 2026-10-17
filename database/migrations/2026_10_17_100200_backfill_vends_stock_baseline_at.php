<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Moves each chiller's ledger baseline from `citybox_status_json` into `vends.stock_baseline_at`
 * (audit STK-02), keeping the later of the two, then drops the JSON keys so no stale second copy
 * is left for anyone to read. Three vends carried one on 2026-10-17.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('vends')
            ->whereRaw("JSON_EXTRACT(citybox_status_json, '$.stock_baseline_at') IS NOT NULL")
            ->orderBy('id')
            ->get(['id', 'stock_baseline_at', 'citybox_status_json'])
            ->each(function ($row) {
                $json = json_decode((string) $row->citybox_status_json, true) ?: [];
                $fromJson = $json['stock_baseline_at'] ?? null;
                $at = $row->stock_baseline_at && $row->stock_baseline_at >= $fromJson ? $row->stock_baseline_at : $fromJson;
                $source = $at === $fromJson ? ($json['stock_baseline_source'] ?? null) : null;

                DB::table('vends')->where('id', $row->id)->update(array_filter([
                    'stock_baseline_at' => $at,
                    'stock_baseline_source' => $source,
                ], fn ($v) => $v !== null));
                DB::table('vends')->where('id', $row->id)->update([
                    'citybox_status_json' => DB::raw("JSON_REMOVE(citybox_status_json, '$.stock_baseline_at', '$.stock_baseline_source')"),
                ]);
            });
    }

    public function down(): void
    {
        // The column stays the record; nothing to put back.
    }
};
