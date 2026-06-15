<?php

namespace App\Console\Commands;

use App\Models\ProductMapping;
use Illuminate\Console\Command;

/**
 * One-off ops command to flip an existing ProductMapping to smart-freezer mode
 * (or back). New mappings should be created via the UI radio — this command
 * exists for migrating already-created mappings without hand-editing SQL.
 *
 * Behavior:
 *   - Sets is_smart = true.
 *   - Seeds basket_layout_json with the default 6-basket × 4-division shape
 *     if it's currently empty (matching the controller's create-time seed).
 *   - Warns (does NOT abort) if existing product_mapping_items have channel
 *     codes that don't look alphanumeric ("11", "12") — the smart editor
 *     expects "1a", "2b" form. You'll want to clear/reassign those items
 *     in the UI after marking.
 *   - --unmark reverts to vending (is_smart = false, basket_layout cleared).
 *
 * Idempotent on repeat runs.
 */
class MarkProductMappingAsSmart extends Command
{
    protected $signature = 'product-mapping:mark-smart
        {id : ProductMapping ID}
        {--unmark : Revert to vending mode}
        {--reset-layout : Force basket_layout_json back to the default (6 baskets × 2 divisions); refuses if any item would be orphaned unless --force is also passed}
        {--force : Skip the orphan-items confirmation when used with --reset-layout}';

    protected $description = 'Mark an existing ProductMapping as a smart-freezer planogram (or revert with --unmark, or reset its basket layout with --reset-layout).';

    public function handle(): int
    {
        $id = (int) $this->argument('id');

        /** @var ProductMapping|null $mapping */
        $mapping = ProductMapping::withoutGlobalScopes()->find($id);

        if (!$mapping) {
            $this->error("ProductMapping #{$id} not found.");
            return self::FAILURE;
        }

        if ($this->option('unmark')) {
            $mapping->is_smart = false;
            $mapping->basket_layout_json = null;
            $mapping->save();
            $this->info("ProductMapping #{$id} ({$mapping->name}) reverted to Vending Machine mode.");
            return self::SUCCESS;
        }

        // Heads-up about channel_code shape — non-blocking, just informational.
        $weirdCodes = $mapping->productMappingItems()
            ->where('channel_code', 'NOT REGEXP', '^[1-6][a-d]?$')
            ->pluck('channel_code')
            ->all();

        if (!empty($weirdCodes)) {
            $this->warn(sprintf(
                'Heads up: %d existing item(s) have channel codes that don\'t match the smart-freezer pattern ([1-6][a-d]?): %s',
                count($weirdCodes),
                implode(', ', array_slice($weirdCodes, 0, 10)) . (count($weirdCodes) > 10 ? '…' : '')
            ));
            $this->warn('Open the Edit page after this command and reassign those items into baskets, or unbind them.');
        }

        $mapping->is_smart = true;

        $defaultLayout = collect(range(1, 6))
            ->map(fn ($basket) => ['basket' => $basket, 'divisions' => 2])
            ->all();

        if ($this->option('reset-layout')) {
            // Items that would no longer be addressable after the reset. The
            // smart UI's cellsFor() only renders codes within the basket's new
            // divisions count, so codes beyond "Xa"/"Xb" (and any non-pattern
            // codes) would silently disappear from the editor without being
            // deleted. Refuse without --force so the operator chooses.
            $orphanItems = $mapping->productMappingItems()
                ->where(function ($q) {
                    $q->where('channel_code', 'NOT REGEXP', '^[1-6][ab]?$');
                })
                ->pluck('channel_code')
                ->all();

            if (!empty($orphanItems) && !$this->option('force')) {
                $this->error(sprintf(
                    'Refusing to reset: %d bound item(s) would be unreachable in the 6×2 default (codes: %s). Re-run with --force to proceed (the rows stay in the DB but stop rendering), or unbind them in the UI first.',
                    count($orphanItems),
                    implode(', ', array_slice($orphanItems, 0, 10)) . (count($orphanItems) > 10 ? '…' : '')
                ));
                return self::FAILURE;
            }

            $mapping->basket_layout_json = $defaultLayout;
            $this->info('Reset basket_layout_json to default (6 baskets × 2 divisions).');
            if (!empty($orphanItems)) {
                $this->warn(sprintf(
                    '%d item(s) are now outside the rendered grid: %s. They remain in product_mapping_items; unbind them in the UI or delete via /items/{id}.',
                    count($orphanItems),
                    implode(', ', $orphanItems)
                ));
            }
        } elseif (empty($mapping->basket_layout_json)) {
            $mapping->basket_layout_json = $defaultLayout;
            $this->info('Seeded default basket layout (6 baskets × 2 divisions).');
        } else {
            $this->info('Existing basket_layout_json preserved (pass --reset-layout to overwrite).');
        }

        $mapping->save();

        $this->info("ProductMapping #{$id} ({$mapping->name}) marked as Smart Freezer.");
        $this->line("Edit it at: /product-mappings/{$id}/edit");

        return self::SUCCESS;
    }
}
