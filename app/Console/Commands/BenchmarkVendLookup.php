<?php

namespace App\Console\Commands;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use Illuminate\Console\Command;

class BenchmarkVendLookup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'benchmark:vend-lookup {code=2752}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Benchmark vend lookup query performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $code = $this->argument('code');

        $this->info("Benchmarking vend lookup for code: {$code}");
        $this->newLine();

        // Warm up
        Vend::withoutGlobalScope(OperatorVendFilterScope::class)
            ->where('code', $code)
            ->first();

        // Benchmark optimized query (current implementation)
        $iterations = 10;
        $totalOptimized = 0;

        $this->info("Running optimized query {$iterations} times...");
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)
                ->where('code', $code)
                ->first();
            $duration = (microtime(true) - $start) * 1000;
            $totalOptimized += $duration;
        }
        $avgOptimized = $totalOptimized / $iterations;

        // Benchmark old query (with global scope and eager loading)
        $totalOld = 0;
        $this->info("Running old query {$iterations} times...");
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $vend = Vend::with('customer')->where('code', $code)->first();
            $duration = (microtime(true) - $start) * 1000;
            $totalOld += $duration;
        }
        $avgOld = $totalOld / $iterations;

        // Display results
        $this->newLine();
        $this->table(
            ['Query Type', 'Avg Time (ms)', 'Total Time (ms)'],
            [
                ['Old (with scope + eager load)', number_format($avgOld, 2), number_format($totalOld, 2)],
                ['Optimized (no scope + lazy load)', number_format($avgOptimized, 2), number_format($totalOptimized, 2)],
            ]
        );

        $improvement = (($avgOld - $avgOptimized) / $avgOld) * 100;
        $this->newLine();
        $this->info("Performance improvement: " . number_format($improvement, 2) . "%");
        $this->info("Speed increase: " . number_format($avgOld / $avgOptimized, 2) . "x faster");

        return Command::SUCCESS;
    }
}
