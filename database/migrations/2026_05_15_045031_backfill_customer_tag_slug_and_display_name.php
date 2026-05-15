<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the role of `tags.name` vs `tags.slug` for Customer-scoped tags
 * (classname = App\Models\Customer):
 *
 *   - `slug` becomes the canonical snake_case identifier used for backend
 *     uniqueness validation and any name/slug lookups elsewhere (it was
 *     already in the schema but had been left unpopulated for Customer tags).
 *   - `name` becomes the user-typed display string shown in the UI.
 *
 * Before this migration:
 *   name = "wants_us_to_stay_in_process"   (snake_case via Tag model mutator)
 *   slug = "" / null
 *
 * After this migration:
 *   name = "Wants Us To Stay In Process"   (Title Case for legacy rows)
 *   slug = "wants_us_to_stay_in_process"
 *
 * Scope is intentionally limited to Customer tags so Product / Vend / Campaign
 * tag flows — which still rely on the snake_case `name` value for label
 * matching in vend_transactions.label_json — are not disturbed. Those flows
 * can adopt the same pattern later if/when needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pull only Customer-scoped tags. Other classnames are left untouched.
        $rows = DB::table('tags')
            ->where('classname', 'App\\Models\\Customer')
            ->get(['id', 'name', 'slug']);

        foreach ($rows as $row) {
            $currentName = (string) ($row->name ?? '');
            $currentSlug = (string) ($row->slug ?? '');

            // 1) Slug: backfill from existing snake_case name when missing.
            //    If a slug is already set we leave it alone so any prior
            //    manual fixes are preserved.
            $newSlug = $currentSlug !== '' ? $currentSlug : $currentName;

            // 2) Display name: convert the snake_case legacy value into
            //    Title Case so existing customer tags stop looking like
            //    database keys in the UI. Pure underscore → space then
            //    ucwords gives the most readable result for legacy data.
            //    Users can re-edit later to refine casing/wording.
            $newName = $this->snakeToTitle($currentName);

            DB::table('tags')
                ->where('id', $row->id)
                ->update([
                    'name' => $newName,
                    'slug' => $newSlug,
                ]);
        }
    }

    public function down(): void
    {
        // Revert Customer-scoped tags back to "name = snake_case, slug = ''"
        // shape so this migration is safely reversible in lower environments.
        $rows = DB::table('tags')
            ->where('classname', 'App\\Models\\Customer')
            ->get(['id', 'name', 'slug']);

        foreach ($rows as $row) {
            // Prefer the slug we stored on up() — it is the authoritative
            // snake_case value. Fall back to re-deriving from the current
            // display name if the slug is somehow blank.
            $restoredName = (string) ($row->slug ?? '');
            if ($restoredName === '') {
                $restoredName = strtolower(trim(preg_replace('/\s+/', '_', (string) $row->name)));
            }

            DB::table('tags')
                ->where('id', $row->id)
                ->update([
                    'name' => $restoredName,
                    'slug' => '',
                ]);
        }
    }

    /**
     * Convert "wants_us_to_stay_in_process" → "Wants Us To Stay In Process".
     * Empty / null in → empty string out, so the DB never sees null on a
     * NOT NULL column.
     */
    private function snakeToTitle(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $spaced = preg_replace('/_+/', ' ', $value);
        $spaced = trim(preg_replace('/\s+/', ' ', $spaced));
        return ucwords(strtolower($spaced));
    }
};
