<?php

namespace App\Console\Commands;

use App\Models\ProductMapping;
use App\Models\Vend;
use Illuminate\Console\Command;

/**
 * Null basket_layout_json on every mapping that is not a Smart Freezer.
 *
 * The column is the freezer's basket grid ([{basket, divisions}]). Until
 * 2026-09-21 the CityBox mirror (ChillerPlanogram::sync, since retired) also
 * wrote a [{layer, positions}] shape onto chiller mappings, and Replicate
 * copies the column verbatim, so ten smart_chiller mappings still carried it
 * on 2026-09-23. The Edit form echoes the column back on Save and the update
 * validator applied the freezer shape to it, so none of those mappings could
 * be saved. update() now nulls it for a non-freezer; this clears the rows
 * that nobody has saved since.
 *
 * Dry run by default; pass --apply to write.
 */
class ClearNonFreezerBasketLayout extends Command
{
    protected $signature = 'product-mappings:clear-basket-layout {--apply : Write the change instead of listing it}';

    protected $description = 'Null basket_layout_json on every non-Smart-Freezer product mapping (dry run unless --apply)';

    public function handle(): int
    {
        $rows = ProductMapping::withoutGlobalScopes()
            ->whereNotNull('basket_layout_json')
            ->where(fn ($q) => $q->where('is_smart', false)->orWhereNull('is_smart'))
            ->where(fn ($q) => $q->where('machine_type', '!=', Vend::MACHINE_TYPE_SMART_FREEZER)->orWhereNull('machine_type'))
            ->orderBy('id')
            ->get(['id', 'name', 'machine_type', 'basket_layout_json']);

        if ($rows->isEmpty()) {
            $this->info('No non-freezer mapping carries a basket layout.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'name', 'machine_type', 'layout'],
            $rows->map(fn ($m) => [$m->id, $m->name, $m->machine_type ?: 'vending_machine', json_encode($m->basket_layout_json)])->all()
        );

        if (! $this->option('apply')) {
            $this->warn("Nothing written. Re-run with --apply to null basket_layout_json on {$rows->count()} mapping(s).");

            return self::SUCCESS;
        }

        $n = ProductMapping::withoutGlobalScopes()->whereIn('id', $rows->pluck('id'))->update(['basket_layout_json' => null]);
        $this->info("Cleared basket_layout_json on {$n} mapping(s).");

        return self::SUCCESS;
    }
}
