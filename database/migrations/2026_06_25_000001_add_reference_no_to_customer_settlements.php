<?php

use App\Models\CustomerSettlement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add the unique human-friendly reference (e.g. LF-000123) to
 * customer_settlements.
 *
 * Separate from the create migration on purpose: the table was already created
 * on some environments before reference_no existed, so the create migration
 * (already recorded as run, and guarded by hasTable) can't add it there. This
 * additive migration is the safe way to patch an already-applied table.
 *
 * Also backfills any pre-existing rows (including a row left behind by a seed
 * that failed on the missing column) using the same prefix-by-type logic as
 * the model, so every row ends up with a stable reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_settlements')) {
            return; // create migration will add the column inline.
        }

        if (!Schema::hasColumn('customer_settlements', 'reference_no')) {
            Schema::table('customer_settlements', function (Blueprint $table) {
                $table->string('reference_no', 20)->nullable()->after('id');
            });
        }

        // Backfill existing rows (query-builder writes — no model events).
        DB::table('customer_settlements')
            ->whereNull('reference_no')
            ->orderBy('id')
            ->get(['id', 'entry_type'])
            ->each(function ($r) {
                $ref = CustomerSettlement::refPrefixFor($r->entry_type)
                    . '-' . str_pad((string) $r->id, 6, '0', STR_PAD_LEFT);
                DB::table('customer_settlements')->where('id', $r->id)->update(['reference_no' => $ref]);
            });

        // Add the unique index once (guard against re-runs).
        if (!$this->hasIndex('customer_settlements', 'customer_settlements_reference_no_unique')) {
            Schema::table('customer_settlements', function (Blueprint $table) {
                $table->unique('reference_no');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_settlements', 'reference_no')) {
            Schema::table('customer_settlements', function (Blueprint $table) {
                if ($this->hasIndex('customer_settlements', 'customer_settlements_reference_no_unique')) {
                    $table->dropUnique('customer_settlements_reference_no_unique');
                }
                $table->dropColumn('reference_no');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($index);
    }
};
