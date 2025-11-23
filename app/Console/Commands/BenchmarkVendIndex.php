<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vend;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\VendController;

class BenchmarkVendIndex extends Command
{
    protected $signature = 'benchmark:vend-index';
    protected $description = 'Benchmark VendController index query';

    public function handle()
    {
        $this->info('Benchmarking VendController::index query...');

        // $controller = new VendController();

        // 1. Slow Path (Simulate sorting by total_stock_amount)
        $this->info('Testing Slow Path (with joins)...');
        $requestSlow = new Request([
            'numberPerPage' => 50,
            'sortKey' => 'total_stock_amount', // Forces join
            'page' => 1,
        ]);

        $start = microtime(true);
        // We can't easily call index() because it returns a view or JSON.
        // But we can simulate the query construction.
        // Actually, let's just call index() and capture the result if possible.
        // The index method returns an Inertia response or View.
        // We might trigger errors if we don't have a full request context.

        // Alternative: Replicate the query logic here.
        $this->runQuery(true);
        $durationSlow = (microtime(true) - $start) * 1000;
        $this->info("Slow Path: {$durationSlow} ms");

        // 2. Fast Path (Default sort)
        $this->info('Testing Fast Path (no joins, post-load)...');
        $requestFast = new Request([
            'numberPerPage' => 50,
            'sortKey' => 'balance_percent', // Default
            'page' => 1,
        ]);

        $start = microtime(true);
        $this->runQuery(false);
        $durationFast = (microtime(true) - $start) * 1000;
        $this->info("Fast Path: {$durationFast} ms");

        $improvement = $durationSlow - $durationFast;
        $this->info("Improvement: {$improvement} ms");
    }

    private function runQuery($needsVc)
    {
        $vends = Vend::query()
            ->with([
                'deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform',
                'vendChannels.product.thumbnail',
                'vendChannels.product.sellingPrices',
                'vendChannels.vendChannelErrorLogs.vendChannelError',
                'modemUnit',
            ])
            ->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('modem_types', 'modem_types.id', '=', 'vends.modem_type_id')
            ->leftJoin('modem_units', 'modem_units.id', '=', 'vends.modem_unit_id')
            ->leftJoin('operators', 'operators.id', '=', 'vends.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->when($needsVc, function ($query) {
                $query->leftJoin(DB::raw('
                (
                    SELECT vend_id, SUM(amount * qty) AS total_stock_amount, SUM(amount * capacity) AS total_full_load_amount
                    FROM vend_channels
                    WHERE is_active = true
                    AND capacity > 0
                    GROUP BY vend_id
                ) AS vc
            '), 'vc.vend_id', '=', 'vends.id');
            })
            ->select('vends.id')
            ->paginate(50);

        if (!$needsVc) {
            // Simulate loadAggregates
            $vendIds = $vends->pluck('id')->toArray();
            if (!empty($vendIds)) {
                DB::table('vend_channels')
                    ->select('vend_id', DB::raw('SUM(amount * qty) as total_stock_amount'))
                    ->whereIn('vend_id', $vendIds)
                    ->where('is_active', true)
                    ->where('capacity', '>', 0)
                    ->groupBy('vend_id')
                    ->get();
            }
        }

        return $vends;
    }
}
