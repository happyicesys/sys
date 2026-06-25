<?php

namespace App\Services;

use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Captures the per-machine end-of-day state for the Ops Performance page.
 *
 * `vends` keeps no history, so the snapshot is always "current state stamped
 * with a date" — which is exactly the end-of-day value when run nightly. The
 * same currentMachineQuery() is reused by the controller's live fallback for
 * days the nightly job hasn't frozen yet.
 *
 * Instance-wide by construction: it reads via DB::table('vends'), so the
 * Eloquent OperatorVendFilterScope never applies.
 */
class OpsMachineDailySnapshotBuilder
{
    public const TABLE = 'ops_machine_daily_snapshots';

    /**
     * Base query: one row per non-disposed machine with denormalized dimension
     * keys and its component/version state. No date — the caller stamps that.
     */
    public static function currentMachineQuery(): Builder
    {
        return DB::table('vends')
            ->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'customers.category_id', '=', 'categories.id')
            ->leftJoin('card_terminals', 'vends.card_terminal_id', '=', 'card_terminals.id')
            ->where('vends.is_disposed', 0)
            ->selectRaw(<<<'SQL'
                vends.id AS vend_id,
                vends.code AS vend_code,
                vends.operator_id AS operator_id,
                vends.vend_prefix_id AS vend_prefix_id,
                vends.vend_model_id AS vend_model_id,
                vends.customer_id AS customer_id,
                customers.location_type_id AS location_type_id,
                customers.category_id AS category_id,
                categories.category_group_id AS category_group_id,
                vends.is_active AS is_active,
                vends.is_testing AS is_testing,
                vends.lcd_monitor_id AS lcd_monitor_id,
                vends.card_terminal_id AS card_terminal_id,
                card_terminals.name AS card_terminal_name,
                CAST(JSON_UNQUOTE(JSON_EXTRACT(vends.parameter_json, '$.BILLStat')) AS SIGNED) AS bill_stat,
                CAST(JSON_UNQUOTE(JSON_EXTRACT(vends.parameter_json, '$.CHGEStat')) AS SIGNED) AS coin_stat,
                UPPER(CONV(JSON_UNQUOTE(JSON_EXTRACT(vends.parameter_json, '$.Ver')), 10, 16)) AS firmware_ver,
                JSON_UNQUOTE(JSON_EXTRACT(vends.apk_ver_json, '$.apkver')) AS apk_ver,
                JSON_UNQUOTE(JSON_EXTRACT(vends.acb_vmc_pa_json, '$.ACBVer')) AS acb_rev
            SQL);
    }

    /**
     * Freeze the current fleet state as the snapshot for $day (delete + insert,
     * so reruns are idempotent — same pattern as gp_metrics).
     */
    public static function persistDay(Carbon $day): int
    {
        $date = $day->toDateString();
        $now = now();
        $inserted = 0;

        // Real per-machine L30d net VendEarning for this date, from gp_metrics.
        $earningByVend = self::l30dVendEarningByVend($day);

        // Real per-machine stock-in value for completed jobs dated this day.
        $stockByVend = self::stockInByVend($day);

        DB::transaction(function () use ($date, $now, $earningByVend, $stockByVend, &$inserted) {
            DB::table(self::TABLE)->where('snapshot_date', $date)->delete();

            self::currentMachineQuery()
                ->orderBy('vends.id')
                ->chunk(1000, function ($rows) use ($date, $now, $earningByVend, $stockByVend, &$inserted) {
                    $payload = [];
                    foreach ($rows as $r) {
                        $payload[] = [
                            'snapshot_date' => $date,
                            'vend_id' => $r->vend_id,
                            'vend_code' => $r->vend_code,
                            'operator_id' => $r->operator_id,
                            'vend_prefix_id' => $r->vend_prefix_id,
                            'vend_model_id' => $r->vend_model_id,
                            'customer_id' => $r->customer_id,
                            'location_type_id' => $r->location_type_id,
                            'category_id' => $r->category_id,
                            'category_group_id' => $r->category_group_id,
                            'is_active' => (int) $r->is_active,
                            'is_testing' => (int) $r->is_testing,
                            'lcd_monitor_id' => $r->lcd_monitor_id,
                            'bill_stat' => $r->bill_stat === null ? null : (int) $r->bill_stat,
                            'coin_stat' => $r->coin_stat === null ? null : (int) $r->coin_stat,
                            'card_terminal_id' => $r->card_terminal_id,
                            'card_terminal_name' => $r->card_terminal_name,
                            'firmware_ver' => $r->firmware_ver,
                            'apk_ver' => $r->apk_ver,
                            'acb_rev' => $r->acb_rev,
                            'l30d_vend_earning_cents' => $earningByVend[$r->vend_id] ?? null,
                            'stock_in_cents' => $stockByVend[$r->vend_id] ?? null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if (! empty($payload)) {
                        DB::table(self::TABLE)->insert($payload);
                        $inserted += count($payload);
                    }
                });
        });

        return $inserted;
    }

    /**
     * Real per-machine L30d net VendEarning ending on $endDate, reconstructed from
     * gp_metrics (full history) plus each machine's current site contract — the
     * same gross − location-fee math the Operation Dashboard and Site Summary use.
     * Because gp_metrics is historical, this is accurate for past dates too, which
     * is what makes back-seeding genuine rather than "today's value stamped on old
     * days".
     *
     * @return array<int,int>  vend_id => net VendEarning cents
     */
    public static function l30dVendEarningByVend(Carbon $endDate): array
    {
        $start = $endDate->copy()->subDays(29)->toDateString();
        $end = $endDate->toDateString();

        // Trailing-30 gross + sales per machine from gp_metrics.
        $windows = DB::table('gp_metrics')
            ->whereBetween('txn_date', [$start, $end])
            ->groupBy('vend_id')
            ->selectRaw('vend_id, COALESCE(SUM(gross_profit_cents),0) AS gross, COALESCE(SUM(amount_cents),0) AS sales')
            ->get();

        if ($windows->isEmpty()) {
            return [];
        }

        // Current site contract per machine (mirrors the dashboard's per-machine
        // fee: vend → customer → operator). DB::table bypasses the Vend scope.
        $contracts = DB::table('vends')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->leftJoin('operators', 'operators.id', '=', 'vends.operator_id')
            ->whereIn('vends.id', $windows->pluck('vend_id')->all())
            ->select(
                'vends.id as vend_id',
                'customers.contract_commission_type as ctype',
                'customers.contract_commission_value as cval',
                'customers.contract_commission_value2 as cval2',
                'customers.contract_ps_term as ps_term',
                'customers.is_external_subsidize as ext_sub',
                'customers.external_subsidize_amount as ext_amt',
                'operators.gst_vat_rate as gst'
            )
            ->get()
            ->keyBy('vend_id');

        $map = [];
        foreach ($windows as $w) {
            $gross = (int) $w->gross;
            $sales = (int) $w->sales;
            $c = $contracts->get($w->vend_id);

            if ($c === null) {
                $map[(int) $w->vend_id] = $gross; // unbinded machine → no location fee
                continue;
            }

            $fee = CustomerSummaryAggregator::computeLocationFeeCents(
                $c->ctype,
                $c->cval !== null ? (float) $c->cval : null,
                $c->cval2 !== null ? (float) $c->cval2 : null,
                $c->ps_term !== null ? (float) $c->ps_term : null,
                $sales,
                $gross,
                (float) ($c->gst ?? 0)
            );

            $extSub = ($c->ext_sub && $c->ext_amt !== null)
                ? (int) round(((float) $c->ext_amt) * 100)
                : 0;

            // VendEarning = Gross − (Location Fee − External Subsidy).
            $map[(int) $w->vend_id] = $gross - ($fee - $extSub);
        }

        return $map;
    }

    /**
     * Per-machine stock-in value (cents) for COMPLETED jobs dated $day —
     * SUM(actual_qty * vend_channel.amount), the same definition the
     * CustomerIndex / Site Summary "stock-in" figures use (vend_channels.amount
     * is stored in cents). status >= 3 && <> 99 = a done, non-voided job.
     *
     * Keyed by the job item's vend_id so it can be stamped onto the matching
     * snapshot row. ops_jobs keeps a real date, so this is accurate for past days
     * too — which is what makes back-seeding genuine. DB::table bypasses the Vend
     * scope (instance-wide by construction).
     *
     * @return array<int,int>  vend_id => stock-in cents
     */
    public static function stockInByVend(Carbon $day): array
    {
        $date = $day->toDateString();

        return DB::table('ops_jobs as oj')
            ->join('ops_job_items as oji', function ($j) {
                $j->on('oji.ops_job_id', '=', 'oj.id')
                    ->where('oji.status', '>=', 3)
                    ->where('oji.status', '<>', 99);
            })
            ->join('ops_job_item_channels as ojic', 'ojic.ops_job_item_id', '=', 'oji.id')
            ->join('vend_channels as vc', 'vc.id', '=', 'ojic.vend_channel_id')
            ->where('oj.date', $date)
            ->whereNotNull('oji.vend_id')
            ->groupBy('oji.vend_id')
            ->selectRaw('oji.vend_id AS vend_id, SUM(ojic.actual_qty * vc.amount) AS cents')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->vend_id => (int) $r->cents])
            ->all();
    }
}
