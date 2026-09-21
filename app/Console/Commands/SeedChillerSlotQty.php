<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off: give every CityBox SKU a starting chiller capacity (2026-09-21).
 *
 * Capacity used to be CityBox's par, copied onto the channel each poll; it is
 * now products.chiller_slot_qty, which is blank everywhere. Rather than start
 * the fleet at zero, seed each product from the par its channels are carrying
 * right now (the largest, when a SKU sits on several machines) and let ops
 * correct it on Product → Edit.
 *
 * Idempotent: a product that already has a value is never overwritten.
 */
class SeedChillerSlotQty extends Command
{
    protected $signature = 'citybox:seed-chiller-capacity {--apply : write the values (default: dry run)}';

    protected $description = "Seed products.chiller_slot_qty from the chiller channels' current capacity";

    public function handle(): int
    {
        $pars = VendChannel::query()
            ->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vends.machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)
            ->where('vend_channels.is_active', true)
            ->whereNotNull('vend_channels.product_id')
            ->where('vend_channels.capacity', '>', 0)
            ->groupBy('vend_channels.product_id')
            ->pluck(DB::raw('MAX(vend_channels.capacity)'), 'vend_channels.product_id');

        if ($pars->isEmpty()) {
            $this->info('No chiller channels with a capacity to learn from.');

            return self::SUCCESS;
        }

        $targets = Product::withoutGlobalScopes()
            ->whereIn('id', $pars->keys())
            ->whereNull('chiller_slot_qty')
            ->get(['id', 'code', 'name']);

        $this->table(
            ['product', 'name', 'capacity'],
            $targets->map(fn (Product $p) => [$p->code, mb_substr((string) $p->name, 0, 40), $pars[$p->id]])->all(),
        );

        if (! $this->option('apply')) {
            $this->warn($targets->count().' product(s) would be seeded — re-run with --apply.');

            return self::SUCCESS;
        }

        foreach ($targets as $product) {
            $product->forceFill(['chiller_slot_qty' => (int) $pars[$product->id]])->save();
        }
        $this->info($targets->count().' product(s) seeded.');

        return self::SUCCESS;
    }
}
