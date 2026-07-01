<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Covering morph index on attachments for the Product::thumbnail relation.
 *
 * thumbnail() is morphOne(Attachment)->ofMany('type', 'min'), which Laravel
 * compiles into nested min(type)/min(id) aggregate subqueries grouped by
 * (modelable_id, modelable_type), filtered by modelable_type = 'App\Models\Product'
 * AND modelable_id IN (...). Laravel's default morphs() index is only
 * (modelable_type, modelable_id), so type/id for the min() aggregates fall to
 * heap lookups.
 *
 * (modelable_type, modelable_id, type) — plus the implicit PK id in the leaf —
 * makes the aggregates index-only: modelable_type equality seek, modelable_id
 * list seek + group key, type/id read straight from the index. Same morph
 * covering-index approach already used on addresses (2026_04_17_200000).
 *
 * attachments is written only on file upload/delete (infrequent), and these key
 * columns are write-once, so the index adds negligible write cost and does not
 * touch any hot path. It supersedes the default (modelable_type, modelable_id)
 * morphs index as a prefix; that default is left in place (dropping it would
 * require confirming its name on a table whose create migration predates this
 * repo — the redundant prefix is cheap on this low-write table).
 */
return new class extends Migration
{
    private string $table = 'attachments';
    private string $index = 'idx_attachments_morph_type';

    public function up(): void
    {
        if (! $this->indexExists($this->index)) {
            DB::statement("ALTER TABLE {$this->table} ADD INDEX {$this->index} (modelable_type, modelable_id, type)");
        }
    }

    public function down(): void
    {
        if ($this->indexExists($this->index)) {
            DB::statement("ALTER TABLE {$this->table} DROP INDEX {$this->index}");
        }
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$this->table}"))
            ->contains(fn ($row) => $row->Key_name === $name);
    }
};
