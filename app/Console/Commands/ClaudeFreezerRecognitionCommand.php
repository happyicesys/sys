<?php

namespace App\Console\Commands;

use App\Models\SmartFreezerClaudeRecognition as ClaudeRow;
use App\Models\SmartFreezerRecognition;
use App\Services\SmartFreezer\Claude\ClaudeCostMoney;
use App\Services\SmartFreezer\Claude\ClaudeModelChoice;
use App\Services\SmartFreezer\Claude\ClaudeShadowRecognitionService;
use App\Services\SmartFreezer\Claude\ClaudeZijiaComparison;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Claude's count beside Zijia's (ClaudeShadowRecognitionService). Every call here is billed by Anthropic.
 *
 *   smart-freezer:claude-recognition 1234            run Claude on Zijia recognition #1234 now
 *   smart-freezer:claude-recognition 1234 --retry    send a failed one again
 *   smart-freezer:claude-recognition --backfill --days=30 [--vend=50001] [--limit=20] [--dry-run]
 *                                                    count past sessions that have no Claude row yet
 *   smart-freezer:claude-recognition --evaluate      judge answers whose sale has arrived since (every 10 min)
 *   smart-freezer:claude-recognition --report --days=30 [--vend=50001] [--details]
 */
class ClaudeFreezerRecognitionCommand extends Command
{
    protected $signature = 'smart-freezer:claude-recognition
        {recognition? : a Zijia recognition id (the AI Recognition row)}
        {--retry : send a failed row again}
        {--backfill : count past sessions with no Claude row yet}
        {--evaluate : judge answers whose sale has arrived since}
        {--report : Claude against Zijia over --days}
        {--details : with --report, one line per session}
        {--days=30}
        {--vend= : a machine code}
        {--limit=0 : with --backfill, at most this many sessions (0 = all)}
        {--dry-run : with --backfill, list what would be sent and send nothing}';

    protected $description = 'Claude counts freezer door sessions beside Zijia (shadow only), and compares the two';

    public function handle(ClaudeShadowRecognitionService $claude): int
    {
        return match (true) {
            (bool) $this->option('evaluate') => $this->evaluate($claude),
            (bool) $this->option('report') => $this->report(),
            (bool) $this->option('backfill') => $this->backfill($claude),
            $this->argument('recognition') !== null => $this->one($claude, (int) $this->argument('recognition')),
            default => $this->usage(),
        };
    }

    private function evaluate(ClaudeShadowRecognitionService $claude): int
    {
        $judged = $claude->evaluateAwaitingSale(max(1, min(30, (int) $this->option('days'))));
        if ($judged > 0) {
            $this->info("{$judged} Claude count(s) now carry a verdict.");
        }

        return self::SUCCESS;
    }

    private function one(ClaudeShadowRecognitionService $claude, int $id): int
    {
        $recognition = SmartFreezerRecognition::find($id);
        if ($recognition === null) {
            $this->error("No recognition #{$id}.");

            return self::FAILURE;
        }
        $row = ClaudeRow::firstOrCreate(
            ['smart_freezer_recognition_id' => $recognition->id],
            ['vend_id' => $recognition->vend_id, 'session_ref' => $recognition->session_ref, 'status' => ClaudeRow::STATUS_PENDING],
        );
        if ($row->status === ClaudeRow::STATUS_DONE) {
            $this->line("Already counted: {$this->describe($claude->evaluate($row))}");

            return self::SUCCESS;
        }
        if (in_array($row->status, [ClaudeRow::STATUS_FAILED, ClaudeRow::STATUS_SKIPPED], true)) {
            if (! $this->option('retry')) {
                $this->warn("Row is {$row->status}: {$row->status_reason}. Add --retry to send it again (billed).");

                return self::FAILURE;
            }
            $row->update(['status' => ClaudeRow::STATUS_PENDING, 'status_reason' => 'sent again by a person']);
        }

        $this->line($this->describe($claude->run($row, byPerson: true)));

        return self::SUCCESS;
    }

    private function backfill(ClaudeShadowRecognitionService $claude): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $query = $this->window(SmartFreezerRecognition::query())
            ->where('status', '!=', SmartFreezerRecognition::STATUS_SKIPPED)
            ->whereNotNull('vend_id')
            ->whereHas('videos')
            ->whereDoesntHave('claude')
            ->orderBy('id');
        $ids = ($limit > 0 ? $query->limit($limit) : $query)->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Nothing to count: every session in the window already has a Claude row.');

            return self::SUCCESS;
        }
        $each = ClaudeModelChoice::estimateText(ClaudeModelChoice::current());
        $this->info(sprintf('%d session(s) to count on %s, about %s each.', $ids->count(), ClaudeModelChoice::current(), $each ?? '?'));
        if ($this->option('dry-run')) {
            $this->line('Dry run: nothing sent. Recognition ids: '.$ids->implode(', '));

            return self::SUCCESS;
        }

        $spent = 0;
        foreach ($ids as $id) {
            $recognition = SmartFreezerRecognition::find($id);
            $row = $recognition ? $claude->openForBackfill($recognition) : null;
            if ($row === null) {
                continue;
            }
            $row = $claude->run($row, byPerson: true);
            $spent += (int) $row->cost_usd_micros;
            $this->line(sprintf('#%d %s', $id, $this->describe($row)));
        }
        $this->info(sprintf('Spent about US$%.2f (%s).', $spent / 1_000_000, ClaudeCostMoney::text($spent)));

        return self::SUCCESS;
    }

    private function report(): int
    {
        $rows = ClaudeRow::with('recognition')
            ->where('created_at', '>=', Carbon::now()->subDays(max(1, (int) $this->option('days'))))
            ->when($this->option('vend'), fn ($q, $code) => $q->whereHas('vend', fn ($v) => $v->where('code', $code)))
            ->orderBy('id')->get();
        $s = ClaudeZijiaComparison::summarise($rows);
        $p = fn ($v) => $v === null ? '–' : $v.'%';

        $this->table(['', 'Claude', 'Zijia'], [
            ['Sessions answered', $s['claude_answered'], $s['zijia_answered']],
            ['Count matches what was paid', "{$s['claude_match_paid']} ({$p($s['claude_match_paid_pct'])})", "{$s['zijia_match_paid']} ({$p($s['zijia_match_paid_pct'])})"],
            ['Right where a person counted', "{$s['claude_right']} of {$s['person_counted']} ({$p($s['claude_right_pct'])})", "{$s['zijia_right']} of {$s['zijia_person_counted']} ({$p($s['zijia_right_pct'])})"],
        ]);
        $this->line("Claude and Zijia gave the same count on {$s['agree']} of {$s['both_answered']} sessions ({$p($s['agree_pct'])}).");
        $this->line("Claude asked for a person on {$s['claude_needs_review']}; failed on {$s['claude_failed']}.");
        $this->line(sprintf('Claude cost: %s in all, %s a session.', $s['cost_text'] ?? '–', $s['cost_per_session_text'] ?? '–'));

        if ($this->option('details')) {
            $metas = ClaudeZijiaComparison::metas($rows);
            $fmt = fn (?array $c) => $c === null ? '–' : (collect($c)->map(fn ($n, $id) => "{$n}×{$id}")->implode(' ') ?: 'nothing');
            $this->table(['Zijia #', 'Session', 'Paid → Claude', 'Zijia', 'Claude', 'Person', 'Cost'], $rows->map(function (ClaudeRow $r) use ($metas, $fmt) {
                $c = ClaudeShadowRecognitionService::counts($r, $r->recognition, $metas[$r->vend_transaction_id ?? $r->recognition?->vend_transaction_id] ?? null);

                return [
                    $r->smart_freezer_recognition_id, $r->session_ref, $r->verdict ?? $r->status,
                    $fmt($c['zijia']), $fmt($c['claude']), $fmt($c['person']),
                    $r->costText() ?? '–',
                ];
            })->all());
        }

        return self::SUCCESS;
    }

    private function window($query)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays(max(1, (int) $this->option('days'))))
            ->when($this->option('vend'), fn ($q, $code) => $q->whereHas('vend', fn ($v) => $v->where('code', $code)));
    }

    private function describe(ClaudeRow $row): string
    {
        $items = collect((array) $row->items)->map(fn ($n, $id) => "{$n}×product {$id}")->implode(', ') ?: 'nothing taken';

        return match ($row->status) {
            ClaudeRow::STATUS_DONE => sprintf('%s; verdict %s; confidence %s%%%s; %s tokens in / %s out; %s',
                $items, $row->verdict ?? 'waiting for the sale', $row->confidence ?? '?', $row->needs_review ? ', wants a person' : '',
                number_format((int) $row->input_tokens), number_format((int) $row->output_tokens),
                $row->costText() ?? '?'),
            default => "{$row->status}: {$row->status_reason}".($row->cost_usd_micros !== null ? ' (billed '.$row->costText().')' : ''),
        };
    }

    private function usage(): int
    {
        $this->line('Give a recognition id, or one of --backfill, --evaluate, --report. Every call is billed.');

        return self::FAILURE;
    }
}
