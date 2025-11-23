<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BenchmarkVendIndexCustomer extends Command
{
    protected $signature = 'benchmark:vend-index-customer';
    protected $description = 'Benchmark VendController indexCustomer query';

    public function handle()
    {
        $this->info('Benchmarking VendController::indexCustomer query...');

        // Warmup
        $this->info('Warming up...');
        $this->runQuery('balance_percent');
        $this->info('Warmup done.');

        // 1. Slow Path (Simulate sorting by total_stock_amount)
        $this->info('Testing Slow Path (with joins)...');
        $start = microtime(true);
        $this->runQuery('total_stock_amount');
        $durationSlow = (microtime(true) - $start) * 1000;
        $this->info("Slow Path: {$durationSlow} ms");

        // 2. Fast Path (Default sort)
        $this->info('Testing Fast Path (no joins, post-load)...');
        $start = microtime(true);
        $this->runQuery('balance_percent');
        $durationFast = (microtime(true) - $start) * 1000;
        $this->info("Fast Path: {$durationFast} ms");

        $improvement = $durationSlow - $durationFast;
        $this->info("Improvement: {$improvement} ms");
    }

    private function runQuery($sortKey)
    {
        $needsVc = in_array($sortKey, ['thirty_days_over_full_load_ratio', 'total_stock_amount', 'total_full_load_amount']);
        // ... other needs omitted for brevity as they are not used in this simplified benchmark

        $vends = Customer::query()
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id');
        // ... standard joins omitted for brevity

        // 1. Count Query (Fast)
        $startCount = microtime(true);
        $countQuery = clone $vends;
        $count = $countQuery->count();
        $countTime = (microtime(true) - $startCount) * 1000;
        $this->info("  Count Time: {$countTime} ms");

        // 2. Data Query (Slow if sorted by expensive column)
        $startData = microtime(true);
        $vends->when($needsVc, function ($query) {
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
            ->select('customers.id');

        if ($needsVc) {
            $vends->orderBy('total_stock_amount', 'desc');
        }

        $items = $vends->forPage(1, 50)->get();
        $dataTime = (microtime(true) - $startData) * 1000;
        $this->info("  Data Time: {$dataTime} ms");
        $this->info("  Total Time: " . ($countTime + $dataTime) . " ms");

        return $items;
    }
}
