<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror the customers @-mention indexes onto products.remarks.
 *
 * The product @-mention badge (NoteNotificationService::productMentionUnreadCount)
 * is the exact twin of the customer one: `remarks IS NOT NULL` + a
 * leading-wildcard `remarks LIKE '%@name%'` + REGEXP, optionally bounded by
 * `remarks_updated_at > $since`. Both run on every page load via the middleware
 * shared-prop closure, so the products side scans too.
 *
 *   - idx_products_remarks_updated_at : seeks the bounded (post-visit) counts.
 *   - idx_products_remarks_prefix     : lets `remarks IS NOT NULL` seek to only
 *                                       the (sparse) remark-bearing products,
 *                                       fixing the unbounded ($since = null) path.
 *
 * Both are behaviour-preserving (index only). A secondary index on remarks /
 * remarks_updated_at is maintained by InnoDB ONLY when those columns change
 * (human note edits) — NOT on the frequent product/availability/stock writes —
 * so no other product query path is slowed.
 */
return new class extends Migration
{
    private string $tsIndex = 'idx_products_remarks_updated_at';
    private string $prefixIndex = 'idx_products_remarks_prefix';

    public function up(): void
    {
        if (Schema::hasColumn('products', 'remarks_updated_at')
            && ! $this->indexExists($this->tsIndex)) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('remarks_updated_at', 'idx_products_remarks_updated_at');
            });
        }

        if (Schema::hasColumn('products', 'remarks')
            && ! $this->indexExists($this->prefixIndex)) {
            // TEXT column needs a prefix length; Laravel can't express it.
            DB::statement("ALTER TABLE products ADD INDEX {$this->prefixIndex} (remarks(191))");
        }
    }

    public function down(): void
    {
        if ($this->indexExists($this->prefixIndex)) {
            DB::statement("ALTER TABLE products DROP INDEX {$this->prefixIndex}");
        }
        if ($this->indexExists($this->tsIndex)) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('idx_products_remarks_updated_at');
            });
        }
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select('SHOW INDEX FROM products'))
            ->contains(fn ($row) => $row->Key_name === $name);
    }
};
