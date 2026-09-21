<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\Vend;
use App\Models\VendChannel;
use Illuminate\Console\Command;

/**
 * One-off, after the planogram hand-over (2026-09-21). Two steps, both idempotent:
 *
 *  1. CAPACITY. It used to be CityBox's par, copied onto the channel each poll;
 *     it is now products.chiller_slot_qty, blank everywhere. Seed each SKU from
 *     the par its channels carry right now (the largest, when it sits on several
 *     machines) so nothing starts at zero, and let ops correct it on Product/Edit.
 *  2. NAMES. The 7 mappings are still called "CityBox <equipment id> (mirror)"
 *     and carry a remark telling ops to edit them in the CityBox portal — both
 *     were true until the hand-over and are now misleading. Rename to the
 *     machine's own ID and restate the remark.
 *
 * A product that already has a capacity, and a mapping that no longer looks like
 * a mirror, are left alone.
 */
class AdoptChillerPlanogram extends Command
{
    protected $signature = 'citybox:adopt-planogram {--apply : write the changes (default: dry run)}';

    protected $description = 'Seed chiller capacities from their par and rename the old mirror mappings';

    private const REMARK = 'CityBox chiller planogram — edited here. Every SKU must also be loaded on the machine in OPS Pro (Pre-Stock Setup), or their AI cannot recognise it.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->seedCapacities($apply);
        $this->renameMirrors($apply);

        if (! $apply) {
            $this->warn('Dry run — re-run with --apply to write.');
        }

        return self::SUCCESS;
    }

    private function seedCapacities(bool $apply): void
    {
        $pars = VendChannel::query()
            ->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vends.machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)
            ->where('vend_channels.is_active', true)
            ->whereNotNull('vend_channels.product_id')
            ->where('vend_channels.capacity', '>', 0)
            ->groupBy('vend_channels.product_id')
            ->selectRaw('vend_channels.product_id as product_id, MAX(vend_channels.capacity) as par')
            ->pluck('par', 'product_id');

        if ($pars->isEmpty()) {
            $this->info('Capacities: no chiller channels with a capacity to learn from.');

            return;
        }

        $targets = Product::withoutGlobalScopes()
            ->whereIn('id', $pars->keys())
            ->whereNull('chiller_slot_qty')
            ->get(['id', 'code', 'name']);

        $this->info('Capacities: '.$targets->count().' product(s) to seed.');
        $this->table(
            ['product', 'name', 'capacity'],
            $targets->map(fn (Product $p) => [$p->code, mb_substr((string) $p->name, 0, 40), $pars[$p->id]])->all(),
        );

        if (! $apply) {
            return;
        }
        foreach ($targets as $product) {
            $product->forceFill(['chiller_slot_qty' => (int) $pars[$product->id]])->save();
        }
        $this->info('Capacities: seeded.');
    }

    private function renameMirrors(bool $apply): void
    {
        $mappings = ProductMapping::withoutGlobalScopes()
            ->where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)
            ->where('name', 'LIKE', '%(mirror)%')
            ->get(['id', 'name', 'remarks']);

        if ($mappings->isEmpty()) {
            $this->info('Names: no mirror mappings left to rename.');

            return;
        }

        $rows = [];
        foreach ($mappings as $mapping) {
            $vend = Vend::withoutGlobalScopes()->where('product_mapping_id', $mapping->id)->first(['id', 'code', 'code_prefix']);
            $name = $vend ? $vend->codeLabel().' planogram' : trim(str_replace('(mirror)', '', $mapping->name));
            $rows[] = [$mapping->id, $mapping->name, $name];
            if ($apply) {
                $mapping->forceFill(['name' => $name, 'remarks' => self::REMARK])->save();
            }
        }

        $this->info('Names: '.count($rows).' mapping(s)'.($apply ? ' renamed.' : ' to rename.'));
        $this->table(['id', 'from', 'to'], $rows);
    }
}
