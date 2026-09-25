<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\RefundTicket;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What in card settlement needs a PERSON — and nothing else (Brian,
 * 2026-09-26: "I need come and run check every week is not a way").
 * Everything the system can decide it now decides itself (matching, late
 * TRADEs, retained-credit re-vends and top-ups, strong terminal moves,
 * Sync); this lists only what it cannot, each item with a link, for the
 * nightly `card-settlement:health` email. An empty result sends nothing.
 */
class CardSettlementHealthCheck
{
    /**
     * @return array<int, array{key:string, title:string, action:string, items: array<int, array{text:string, url:?string}>}>
     */
    public function run(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $from = $today->copy()->subDays(14);
        $base = rtrim((string) config('app.url'), '/');

        $sections = [
            $this->missingFiles($today, $base),
            $this->unsyncedReports($today, $base),
            $this->pendingMoves($from, $base),
            $this->openRefundsOnRetries($base),
            $this->machinesWithoutCompany($from, $base),
            $this->doubleBoundTerminals(),
            $this->foreignMatches($from, $base),
            $this->unexplainedNoLine($from, $today),
            $this->danglingLines($from, $base),
        ];

        return array_values(array_filter($sections, fn ($s) => ! empty($s['items'])));
    }

    /** NETS files are the one thing a person still fetches: a day with none. */
    protected function missingFiles(Carbon $today, string $base): array
    {
        $have = CardSettlementReport::query()->whereNotNull('cutover_date')
            ->whereBetween('cutover_date', [$today->copy()->subDays(14)->toDateString(), $today->copy()->subDays(2)->toDateString()])
            ->pluck('cutover_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->flip();
        $items = [];
        for ($d = $today->copy()->subDays(14); $d->lte($today->copy()->subDays(2)); $d->addDay()) {
            if (! $have->has($d->toDateString())) {
                $items[] = ['text' => 'No NETS file uploaded for '.$d->toDateString(), 'url' => $base.'/card-settlements'];
            }
        }

        return ['key' => 'missing_files', 'title' => 'NETS files not uploaded', 'action' => 'Download them from MerchantConnect and upload them; matching and Sync then run by themselves.', 'items' => $items];
    }

    protected function unsyncedReports(Carbon $today, string $base): array
    {
        $items = CardSettlementReport::query()
            ->where('status', '!=', CardSettlementReport::STATUS_SYNCED)
            ->where('created_at', '<', $today->copy()->subDay())
            ->where('cutover_date', '>=', $today->copy()->subDays(21)->toDateString())
            ->orderBy('cutover_date')->get()
            ->map(fn ($r) => ['text' => "Report #{$r->id} ({$r->cutover_date?->toDateString()}) is still '{$r->status}'".($r->error_message ? ': '.mb_substr($r->error_message, 0, 120) : ''), 'url' => $base.'/card-settlements/'.$r->id])
            ->all();

        return ['key' => 'unsynced', 'title' => 'Reports not synced', 'action' => 'Matching or the automatic Sync failed — open the report and press Rematch.', 'items' => $items];
    }

    /** Lines that point at a terminal move the evidence is too weak to apply by itself. */
    protected function pendingMoves(Carbon $from, string $base): array
    {
        $rows = CardSettlementRow::query()
            ->where('status', CardSettlementRow::STATUS_UNMATCHED)
            ->where(fn ($q) => $q->where('resolution_note', 'like', 'No matching sale on bound machine%')->orWhere('resolution_note', 'No terminal binding'))
            ->where('transaction_date', '>=', $from->toDateString())
            ->where('amount_cents', '>', 0)
            // Test-rig amounts are swept nightly and prove nothing about a terminal.
            ->whereNotIn('amount_cents', VendTransaction::ODD_TRANSACTION_AMOUNTS)
            ->get(['card_settlement_report_id', 'terminal_id', 'transaction_date', 'resolution_note', 'amount_cents']);
        $items = $rows->groupBy('terminal_id')->map(function ($g, $tid) use ($base) {
            $last = $g->sortByDesc('transaction_date')->first();
            $where = str_starts_with($last->resolution_note, 'No terminal binding') ? 'has no machine bound' : 'sells on another machine? ('.mb_substr($last->resolution_note, 36).')';

            return ['text' => "Terminal {$tid} {$where} — {$g->count()} line(s), last {$last->transaction_date->toDateString()}", 'url' => $base.'/card-settlements/'.$last->card_settlement_report_id];
        })->values()->all();

        return ['key' => 'pending_moves', 'title' => 'Terminals that may have moved (evidence too weak to move automatically)', 'action' => 'If you know where the terminal is, set it on that machine\'s Setting/Edit page; otherwise it resolves itself once more sales arrive.', 'items' => $items];
    }

    /** Open refund claims on a failed sale whose customer got the item on retry. */
    protected function openRefundsOnRetries(string $base): array
    {
        $closed = [RefundTicket::STATUS_COMPLETED, RefundTicket::STATUS_REJECTED, RefundTicket::STATUS_AUTO_RESOLVED];
        $items = DB::table('refund_tickets as t')
            ->join('vend_transactions as c', function ($j) {
                $j->on('c.retained_credit_settles_txn_id', '=', 't.vend_transaction_id')->where('c.is_retained_credit_settlement', true);
            })
            ->whereNotIn('t.status', $closed)
            ->whereNull('t.deleted_at')
            ->get(['t.id', 't.reference', 't.status', 't.vend_code'])
            ->unique('id')
            ->map(fn ($t) => ['text' => "{$t->reference} ({$t->status}, machine {$t->vend_code}) — the customer got the item on a retry", 'url' => $base.'/refunds/'.$t->id])
            ->values()->all();

        return ['key' => 'refunds_on_retries', 'title' => 'Refund claims where the item was received on retry', 'action' => 'Reject or adjust before approving — the vend failed, but the retained credit served the customer\'s next selection.', 'items' => $items];
    }

    protected function machinesWithoutCompany(Carbon $from, string $base): array
    {
        $items = DB::table('card_settlement_rows as r')
            ->join('vend_transactions as v', 'v.id', '=', 'r.matched_vend_transaction_id')
            ->join('vends as vd', 'vd.id', '=', 'v.vend_id')
            ->whereNull('vd.card_terminal_id')
            ->whereNotIn('vd.code', VendTransaction::ODD_TRANSACTION_RETAIN_VEND_CODES)
            ->where('r.status', CardSettlementRow::STATUS_MATCHED)
            ->where('r.transaction_date', '>=', $from->toDateString())
            ->groupBy('vd.id', 'vd.code')
            ->selectRaw('vd.id, vd.code, COUNT(*) n')
            ->get()
            ->map(fn ($v) => ['text' => "Machine {$v->code} sells on NETS ({$v->n} lines) but has no card reader company set", 'url' => $base.'/settings/vend/'.$v->id.'/update'])
            ->all();

        return ['key' => 'no_company', 'title' => 'Machines missing their card reader company', 'action' => 'Set "Nets" (or the right company) on the machine\'s Setting/Edit page.', 'items' => $items];
    }

    protected function doubleBoundTerminals(): array
    {
        $items = DB::table('card_terminal_bindings as a')
            ->join('card_terminal_bindings as b', function ($j) {
                $j->on('b.terminal_id', '=', 'a.terminal_id')->on('b.id', '>', 'a.id')->on('b.vend_id', '<>', 'a.vend_id');
            })
            ->join('vends as va', 'va.id', '=', 'a.vend_id')
            ->join('vends as vb', 'vb.id', '=', 'b.vend_id')
            ->whereRaw('(a.until_at IS NULL OR a.until_at > a.from_at) AND (b.until_at IS NULL OR b.until_at > b.from_at)')
            // More than a day: a legacy date-only swap overlaps for exactly the
            // swap day, which the newest-row-wins rule already resolves.
            ->whereRaw("GREATEST(COALESCE(a.from_at,'1970-01-01'), COALESCE(b.from_at,'1970-01-01')) < LEAST(COALESCE(a.until_at,'2100-01-01'), COALESCE(b.until_at,'2100-01-01')) - INTERVAL 1 DAY")
            ->where(fn ($q) => $q->whereNull('a.until_at')->orWhereNull('b.until_at')->orWhere('a.until_at', '>=', now()->subDays(45))->orWhere('b.until_at', '>=', now()->subDays(45)))
            ->get(['a.terminal_id', 'va.code as va', 'vb.code as vb', 'a.from_at as af', 'a.until_at as au', 'b.from_at as bf', 'b.until_at as bu'])
            ->map(fn ($r) => ['text' => "Terminal {$r->terminal_id} is bound to {$r->va} (".substr((string) $r->af, 0, 10).' → '.($r->au ? substr($r->au, 0, 10) : 'now').") and {$r->vb} (".substr((string) $r->bf, 0, 10).' → '.($r->bu ? substr($r->bu, 0, 10) : 'now').') at the same time', 'url' => null])
            ->all();

        return ['key' => 'double_bound', 'title' => 'Terminals bound to two machines at once', 'action' => 'End the wrong binding on that machine\'s Setting/Edit page.', 'items' => $items];
    }

    protected function foreignMatches(Carbon $from, string $base): array
    {
        $foreign = CardSettlementMatcher::foreignCompanyIds('nets');
        if (! $foreign) {
            return ['key' => 'foreign', 'title' => '', 'action' => '', 'items' => []];
        }
        $items = DB::table('card_settlement_rows as r')
            ->join('vend_transactions as v', 'v.id', '=', 'r.matched_vend_transaction_id')
            ->join('vends as vd', 'vd.id', '=', 'v.vend_id')
            ->whereIn('vd.card_terminal_id', $foreign)
            ->where('r.status', CardSettlementRow::STATUS_MATCHED)
            ->where('r.transaction_date', '>=', $from->toDateString())
            ->get(['r.card_settlement_report_id', 'r.terminal_id', 'vd.code', 'r.transaction_date'])
            ->map(fn ($r) => ['text' => "NETS line of {$r->terminal_id} on {$r->transaction_date} is matched to machine {$r->code}, whose reader is not NETS", 'url' => $base.'/card-settlements/'.$r->card_settlement_report_id])
            ->all();

        return ['key' => 'foreign', 'title' => 'NETS lines matched to non-NETS machines', 'action' => 'Use Assign / Ignore on the line.', 'items' => $items];
    }

    /** Days whose dispensed-but-never-charged count is above normal. */
    protected function unexplainedNoLine(Carbon $from, Carbon $today): array
    {
        $threshold = (int) config('card_settlement.alert_unexplained_per_day', 12);
        $items = DB::table('vend_transactions as v')
            ->join('vends as vd', 'vd.id', '=', 'v.vend_id')
            ->where('v.card_settlement_state', CardSettlementRefundReconciler::STATE_NOT_CAPTURED)
            ->where('v.is_retained_credit_settlement', false)
            ->where('v.success_qty', '>', 0)
            ->whereBetween('v.transaction_datetime', [$from, $today])
            ->groupByRaw('DATE(v.transaction_datetime)')
            ->selectRaw("DATE(v.transaction_datetime) d, COUNT(*) n, GROUP_CONCAT(DISTINCT vd.code ORDER BY vd.code SEPARATOR ', ') machines")
            ->havingRaw('COUNT(*) > ?', [$threshold])
            ->get()
            ->map(fn ($r) => ['text' => "{$r->d}: {$r->n} dispensed card sales with no NETS charge (normal ≈ 6) — machines {$r->machines}", 'url' => null])
            ->all();

        return ['key' => 'unexplained', 'title' => 'More dispensed-but-not-charged card sales than usual', 'action' => 'Look for a terminal that stopped settling or a new pattern; these are goods given away unless NETS charges them later.', 'items' => $items];
    }

    protected function danglingLines(Carbon $from, string $base): array
    {
        $items = DB::table('card_settlement_rows as r')
            ->leftJoin('vend_transactions as v', 'v.id', '=', 'r.matched_vend_transaction_id')
            ->where('r.status', CardSettlementRow::STATUS_MATCHED)
            ->where('r.is_reversal', false)
            ->whereNotNull('r.matched_vend_transaction_id')
            ->whereNull('v.id')
            ->whereNotIn('r.amount_cents', VendTransaction::ODD_TRANSACTION_AMOUNTS) // swept test sales: released nightly
            ->where('r.transaction_date', '>=', $from->toDateString())
            ->get(['r.card_settlement_report_id', 'r.terminal_id', 'r.transaction_date', 'r.amount_cents'])
            ->map(fn ($r) => ['text' => "Line {$r->terminal_id} {$r->transaction_date} \$".number_format($r->amount_cents / 100, 2).' points at a sale that no longer exists', 'url' => $base.'/card-settlements/'.$r->card_settlement_report_id])
            ->all();

        return ['key' => 'dangling', 'title' => 'Lines pointing at deleted sales', 'action' => 'Press Rematch on the report.', 'items' => $items];
    }
}
