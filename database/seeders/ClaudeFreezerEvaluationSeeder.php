<?php

namespace Database\Seeders;

use App\Models\SmartFreezerClaudeRecognition as ClaudeRow;
use App\Models\SmartFreezerRecognition;
use App\Services\SmartFreezer\Claude\ClaudeAccuracyEvaluation;
use App\Services\SmartFreezer\Claude\ClaudeCostMoney;
use App\Services\SmartFreezer\Claude\ClaudeModelChoice;
use App\Services\SmartFreezer\Claude\ClaudeShadowRecognitionService;
use App\Services\SmartFreezer\Claude\FreezerCounter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Claude counts every past freezer door session it has not counted yet, then reports how close it came to the final
 * result and what a correct result costs (Brian, 2026-11-13). BILLED by Anthropic for every session it sends.
 *
 *   php artisan db:seed --class=ClaudeFreezerEvaluationSeeder --force
 *   CLAUDE_EVAL_LIMIT=3 php artisan db:seed --class=ClaudeFreezerEvaluationSeeder --force    (try a few first)
 *   CLAUDE_EVAL_REPORT_ONLY=1 php artisan db:seed --class=ClaudeFreezerEvaluationSeeder --force (no calls, report again)
 *
 * Does nothing until ANTHROPIC_API_KEY is set and CLAUDE_FREEZER_AI=true. Safe to run again: a session Claude already
 * counted is never sent twice, and `monthly_budget_usd` stops it. Each session goes through the same path as a live one
 * (ClaudeShadowRecognitionService::run, blind to what was paid) with the model picked on AI Recognition. The final
 * result is a person's Adjust Qty, else Zijia's count where it matched what was paid (ClaudeAccuracyEvaluation).
 * Every session is listed in storage/app/claude-eval/eval-<time>.csv.
 */
class ClaudeFreezerEvaluationSeeder extends Seeder
{
    public function run(ClaudeShadowRecognitionService $claude, FreezerCounter $counter): void
    {
        $reportOnly = (bool) env('CLAUDE_EVAL_REPORT_ONLY', false);
        if (! $reportOnly) {
            if (! config('smart_freezer.claude_recognition.enabled') || ! $counter->isConfigured()) {
                $this->command?->warn('Nothing sent: set ANTHROPIC_API_KEY and CLAUDE_FREEZER_AI=true first.');

                return;
            }
            $this->countPast($claude);
        }

        $rows = ClaudeRow::with(['recognition', 'vend'])->orderBy('id')->get();
        $eval = ClaudeAccuracyEvaluation::evaluate($rows);
        foreach (ClaudeAccuracyEvaluation::reportLines($eval) as $line) {
            $this->command?->line($line);
        }
        $path = storage_path('app/claude-eval/eval-'.now()->format('Ymd-His').'.csv');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, ClaudeAccuracyEvaluation::csv($eval));
        $this->command?->info("Every session: {$path}");
    }

    private function countPast(ClaudeShadowRecognitionService $claude): void
    {
        $limit = max(0, (int) env('CLAUDE_EVAL_LIMIT', 0));
        $query = SmartFreezerRecognition::query()
            ->whereNotNull('vend_id')
            ->where('status', '!=', SmartFreezerRecognition::STATUS_SKIPPED)
            ->whereHas('videos')
            ->whereDoesntHave('claude')
            ->orderBy('id');
        $ids = ($limit > 0 ? $query->limit($limit) : $query)->pluck('id');

        $model = ClaudeModelChoice::current();
        $budget = (float) config('smart_freezer.claude_recognition.monthly_budget_usd');
        $this->command?->info(sprintf('%d past session(s) to count on %s, about %s each. Spent this month: %s of the US$%s budget.',
            $ids->count(), $model, ClaudeModelChoice::estimateText($model) ?? '?',
            ClaudeCostMoney::text($claude->spentThisMonthUsdMicros()) ?? '0', $budget));

        foreach ($ids as $id) {
            $recognition = SmartFreezerRecognition::find($id);
            $row = $recognition ? $claude->openForBackfill($recognition) : null;
            if ($row === null) {
                continue;
            }
            $row = $claude->run($row, byPerson: true);
            $this->command?->line(sprintf('#%d %s → %s%s%s', $id, $row->session_ref ?? '', $row->status,
                $row->verdict ? ' '.$row->verdict : '', $row->costText() ? ' · '.$row->costText() : ''));
            if ($row->status === ClaudeRow::STATUS_SKIPPED && str_starts_with((string) $row->status_reason, 'monthly Claude budget')) {
                // Leave the session uncounted so a later run picks it up.
                $row->delete();
                $this->command?->warn('Stopped: '.$row->status_reason.'.');
                break;
            }
        }
    }
}
