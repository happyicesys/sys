<?php

namespace App\Console\Commands;

use App\Models\Operator;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Time each TransactionIndex sub-query in isolation to find production bottlenecks.
 * Safe to run in production — read-only, no side effects.
 *
 * Usage:
 *   php artisan transaction-index:debug
 *   php artisan transaction-index:debug --operator=1         # filter by operator ID
 *   php artisan transaction-index:debug --no-cache           # bypass cache, force DB hits
 *   php artisan transaction-index:debug --date=2026-04-01    # specific date (default: today)
 *   php artisan transaction-index:debug --per-page=100       # rows per page (default: 50)
 */
class DebugTransactionIndexPerformance extends Command
{
    protected $signature = 'transaction-index:debug
                            {--operator=  : Operator ID to filter by (leave blank = use first operator)}
                            {--no-cache   : Bypass cache and hit DB directly}
                            {--date=      : Date to filter (YYYY-MM-DD, default: today)}
                            {--per-page=50 : Rows per page for the paginated query}';

    protected $description = 'Time each TransactionIndex sub-query individually to find the production bottleneck';

    public function handle(): void
    {
        $operatorId = $this->option('operator');
        $noCache    = $this->option('no-cache');
        $perPage    = (int) $this->option('per-page') ?: 50;
        $dateStr    = $this->option('date') ?: Carbon::today()->toDateString();

        // Resolve operator
        $operator = $operatorId
            ? Operator::find($operatorId)
            : Operator::first();

        if (! $operator) {
            $this->error('No operator found. Pass --operator=<id>');
            return;
        }

        // HIPL multi-operator expansion (mirrors transactionIndex logic)
        if ($operator->code === 'HIPL') {
            $relatedCodes = ['HIPL', 'HIMD', 'LEA', 'HIESG', 'UL-ST'];
            $operatorIds  = Operator::whereIn('code', $relatedCodes)->pluck('id')->filter()->values()->toArray();
        } else {
            $operatorIds = [$operator->id];
        }

        $dateFrom = Carbon::parse($dateStr)->startOfDay();
        $dateTo   = Carbon::parse($dateStr)->endOfDay();

        $this->info("Operator : {$operator->name} (#{$operator->id})" . ($operator->code === 'HIPL' ? ' [HIPL group: ' . implode(', ', $operatorIds) . ']' : ''));
        $this->info("Date     : {$dateFrom->toDateString()} (start of day → end of day)");
        $this->info("Cache    : " . ($noCache ? 'BYPASSED' : 'enabled'));
        $this->info("PerPage  : {$perPage}");
        $this->newLine();

        // ── 1. Base count query ───────────────────────────────────────────────
        // Mirrors $totalTransactions = (clone $baseQuery)->count() path.
        $this->bench('Base COUNT (operator + date filter only)', function () use ($operatorIds, $dateFrom, $dateTo) {
            return VendTransaction::query()
                ->whereIn('vend_transactions.operator_id', $operatorIds)
                ->where('vend_transactions.transaction_datetime', '>=', $dateFrom)
                ->where('vend_transactions.transaction_datetime', '<=', $dateTo)
                ->count();
        });

        // ── 2. Paginated records — DEFERRED JOIN (production pattern) ────────
        // Step A: fetch page IDs from lightweight base query (no joins → index-only scan).
        // Step B: join only for those N rows → O(page_size × joins) not O(all_rows × joins).
        $rowCount = null;
        $pageIdsForBench = [];
        $this->bench("Deferred join — step A: fetch {$perPage} IDs (index-only, no joins)", function () use ($operatorIds, $dateFrom, $dateTo, $perPage, &$pageIdsForBench) {
            $pageIdsForBench = DB::table('vend_transactions')
                ->whereIn('operator_id', $operatorIds)
                ->where('transaction_datetime', '>=', $dateFrom)
                ->where('transaction_datetime', '<=', $dateTo)
                ->orderBy('transaction_datetime', 'desc')
                ->limit($perPage)
                ->pluck('id')
                ->all();
            return collect($pageIdsForBench);
        });

        $this->bench("Deferred join — step B: fetch {$perPage} rows with all joins (whereIn IDs)", function () use ($pageIdsForBench, &$rowCount) {
            if (empty($pageIdsForBench)) {
                $rowCount = 0;
                return collect();
            }
            $idList = implode(',', array_map('intval', $pageIdsForBench));
            $rows = VendTransaction::query()
                ->whereIn('vend_transactions.id', $pageIdsForBench)
                ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
                ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
                ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
                ->leftJoin('products', 'products.id', '=', 'vend_transactions.product_id')
                ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_transactions.vend_channel_id')
                ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
                ->join('vends', 'vends.id', '=', 'vend_transactions.vend_id')
                ->leftJoin('vend_contracts', 'vend_contracts.id', '=', 'vends.vend_contract_id')
                ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vend_transactions.vend_prefix_id')
                ->select([
                    'vend_transactions.id',
                    'vend_transactions.order_id',
                    'vend_transactions.transaction_datetime',
                    'vends.code AS vend_code',
                    'vend_prefixes.name AS vend_prefix_name',
                    'customers.code AS customer_code',
                    'customers.name AS customer_name',
                    'customers.virtual_customer_prefix',
                    'customers.virtual_customer_code',
                    'operators.code AS operator_code',
                    'vend_transactions.vend_channel_code',
                    'products.code AS product_code',
                    'products.name AS product_name',
                    'vend_channels.amount AS vend_channel_amount',
                    'vend_transactions.amount',
                    'payment_methods.name AS payment_method_name',
                    'vend_channel_errors.desc AS vend_channel_error_desc',
                    'vend_channel_errors.code AS vend_channel_error_code',
                    'vend_contracts.name AS vend_contract_name',
                    'vend_transactions.interface_type',
                    'vend_transactions.is_multiple',
                    'vend_transactions.is_refunded',
                    'vend_transactions.is_payment_received',
                    'vend_transactions.items_json',
                    'vend_transactions.label_json',
                ])
                ->orderByRaw("FIELD(vend_transactions.id, {$idList})")
                ->get();
            $rowCount = $rows->count();
            return $rows;
        });
        $this->line("  → {$rowCount} rows returned");
        $this->newLine();

        // ── 3. Totals aggregation — whereNotIn testing vends (production pattern) ──
        // Eliminates INNER JOIN vends (was forcing scan of all matching rows × vends table).
        // Instead: pre-fetch testing vend IDs once, push as IN list → index probe per ID.
        $testingVendIds = DB::table('vends')->where('is_testing', true)->pluck('id')->map(fn($v) => (int)$v)->all();
        $this->bench('Totals aggregation (whereNotIn testing vends, no vends JOIN)', function () use ($operatorIds, $dateFrom, $dateTo, $testingVendIds) {
            $q = VendTransaction::query()
                ->whereIn('vend_transactions.operator_id', $operatorIds)
                ->where('vend_transactions.transaction_datetime', '>=', $dateFrom)
                ->where('vend_transactions.transaction_datetime', '<=', $dateTo)
                ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
                ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
                ->leftJoin('delivery_platform_orders', 'delivery_platform_orders.vend_transaction_id', '=', 'vend_transactions.id');
            if (!empty($testingVendIds)) {
                $q->whereNotIn('vend_transactions.vend_id', $testingVendIds);
            }
            return $q->select([
                    DB::raw('CAST(COUNT(CASE WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true THEN 1 ELSE NULL END) AS SIGNED) AS success_count'),
                    DB::raw('COUNT(*) AS total_count'),
                    DB::raw('ROUND(COALESCE(SUM(CASE WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true THEN vend_transactions.amount ELSE 0 END), 0), 2) AS success_amount'),
                    DB::raw('ROUND(COALESCE(SUM(CASE WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true) AND delivery_platform_orders.id IS NULL AND payment_methods.code = 0 THEN vend_transactions.amount ELSE 0 END), 0), 2) AS cash_amount'),
                    DB::raw('ROUND(COALESCE(SUM(CASE WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true) AND delivery_platform_orders.id IS NULL AND payment_methods.payment_gateway_id IS NULL AND payment_methods.code > 0 THEN vend_transactions.amount ELSE 0 END), 0), 2) AS cashless_terminal_amount'),
                    DB::raw('ROUND(COALESCE(SUM(CASE WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true) AND delivery_platform_orders.id IS NULL AND payment_methods.payment_gateway_id IS NOT NULL THEN vend_transactions.amount ELSE 0 END), 0), 2) AS qr_payment_amount'),
                    DB::raw('CAST(SUM(CASE WHEN is_multiple = 0 THEN 1 ELSE 0 END) AS SIGNED) as single_qty'),
                ])
                ->first();
        });

        // ── 4. Item totals — whereNotIn testing vends (production pattern) ───
        // Mirrors the $itemTotals = VendTransaction::query()->...->first() path.
        $this->bench('Item totals (whereNotIn testing vends, vend_transaction_items join)', function () use ($operatorIds, $dateFrom, $dateTo, $testingVendIds) {
            $q = VendTransaction::query()
                ->whereIn('vend_transactions.operator_id', $operatorIds)
                ->where('vend_transactions.transaction_datetime', '>=', $dateFrom)
                ->where('vend_transactions.transaction_datetime', '<=', $dateTo)
                ->where('is_multiple', true);
            if (!empty($testingVendIds)) {
                $q->whereNotIn('vend_transactions.vend_id', $testingVendIds);
            }
            return $q->leftJoin('vend_transaction_items', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
                ->select([
                    DB::raw('COUNT(*) as total_items'),
                    DB::raw('COUNT(CASE WHEN vend_transaction_items.id IS NOT NULL AND (vend_transaction_items.vend_channel_error_code IN (0,6) OR vend_transaction_items.vend_channel_error_code IS NULL) THEN 1 END) as success_items')
                ])
                ->first();
        });

        // ── 5. Count-only baseline (no joins, raw date+operator) ─────────────
        // Isolates the index scan cost without any join overhead.
        $this->bench('Raw COUNT on vend_transactions (no joins, idx_operator_datetime)', function () use ($operatorIds, $dateFrom, $dateTo) {
            return DB::table('vend_transactions')
                ->whereIn('operator_id', $operatorIds)
                ->where('transaction_datetime', '>=', $dateFrom)
                ->where('transaction_datetime', '<=', $dateTo)
                ->count();
        });

        // ── 6. Totals query — date range only, no operator filter ─────────────
        // If this is slow, the transaction_datetime index alone is insufficient.
        $this->bench('Raw COUNT on vend_transactions (date only, no operator filter)', function () use ($dateFrom, $dateTo) {
            return DB::table('vend_transactions')
                ->where('transaction_datetime', '>=', $dateFrom)
                ->where('transaction_datetime', '<=', $dateTo)
                ->count();
        });

        // ── 7. Totals query isolated — delivery_platform_orders join cost ─────
        // delivery_platform_orders has a LEFT JOIN on vend_transaction_id;
        // if the index is missing, this join forces a full scan.
        $this->bench('Totals: isolate delivery_platform_orders LEFT JOIN cost', function () use ($operatorIds, $dateFrom, $dateTo) {
            return DB::table('vend_transactions')
                ->whereIn('vend_transactions.operator_id', $operatorIds)
                ->where('vend_transactions.transaction_datetime', '>=', $dateFrom)
                ->where('vend_transactions.transaction_datetime', '<=', $dateTo)
                ->leftJoin('delivery_platform_orders', 'delivery_platform_orders.vend_transaction_id', '=', 'vend_transactions.id')
                ->select([DB::raw('COUNT(*) AS cnt'), DB::raw('COUNT(delivery_platform_orders.id) AS dpo_cnt')])
                ->first();
        });

        // ── 8. Eager-load vendTransactionItems for page rows ─────────────────
        // Mirrors ->with(['vendTransactionItems.product', 'vendTransactionItems.vendChannelError'])
        // Uses the page's transaction IDs to simulate the eager-load.
        $txIds = DB::table('vend_transactions')
            ->whereIn('operator_id', $operatorIds)
            ->where('transaction_datetime', '>=', $dateFrom)
            ->where('transaction_datetime', '<=', $dateTo)
            ->orderBy('transaction_datetime', 'desc')
            ->limit($perPage)
            ->pluck('id')
            ->toArray();

        $this->bench("Eager-load vendTransactionItems for {$perPage} transactions", function () use ($txIds) {
            if (empty($txIds)) return collect();
            return DB::table('vend_transaction_items')
                ->whereIn('vend_transaction_id', $txIds)
                ->get();
        });

        // ── 9. Metadata / dropdown caches ────────────────────────────────────
        $this->bench('Metadata dropdowns (categories, operators, payment_methods, etc.) — cached 24h', function () use ($noCache) {
            $keys = [
                'categories_' . get_class(new \App\Models\Customer()),
                'operator_options',
                'payment_methods',
                'vend_channel_errors',
                'vend_contract_options',
                'vend_model_options',
                'vend_prefix_options_active',
                'tag_options_product',
                'location_type_options',
            ];
            if ($noCache) {
                foreach ($keys as $k) Cache::forget($k);
            }
            return collect($keys)->map(fn($k) => Cache::get($k))->filter()->count();
        });
    }

    // ── Benchmark helper ──────────────────────────────────────────────────────

    private function bench(string $label, callable $fn): void
    {
        $start  = microtime(true);
        $result = $fn();
        $ms     = round((microtime(true) - $start) * 1000);

        $rows = match (true) {
            $result instanceof \Illuminate\Support\Collection => $result->count(),
            is_array($result)                                 => count($result),
            is_int($result)                                   => $result,
            default                                           => '?',
        };

        $icon = match (true) {
            $ms < 1000  => '🟢',
            $ms < 5000  => '🟡',
            default     => '🔴',
        };

        $this->line("  {$label} ... {$icon} {$ms}ms ({$rows} rows)");
    }
}
