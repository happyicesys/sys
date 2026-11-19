<?php

namespace App\Services\CardTerminal;

use App\Models\RemoteCardTerminal;
use App\Models\RemoteCardTerminalStatusSpan as Span;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The online / offline history of each remote card terminal (Payrallel T05) — Brian, 2026-11-19: "keep
 * tracking the online and offline log … upload to mark1 to keep". The only writer of
 * remote_card_terminal_status_spans.
 *
 * Every answer Payrallel gives about a terminal goes through {@see record()}: the freezer's readiness poll
 * (every 30 s, every 3 s on its payment page), the panel's Check terminal, and mark1's own check of every BOUND
 * terminal every 30 s (`card-terminals:watch-status`), which keeps the log going when a freezer is off.
 * A row is one stretch of the same answer; it only grows while the answers agree and no check is missed for
 * longer than {@see GAP_SECONDS}. A gap longer than that is time nobody checked, shown as such, never guessed.
 *
 * "Online" is Payrallel's word for the T05's own 4G link to its cloud. It says nothing about the freezer, and a
 * failed check (Payrallel unreachable) is kept apart (`online` NULL) so their outage is never read as ours.
 */
class TerminalStatusLog
{
    /** Two checks further apart than this leave the time between them unchecked. */
    public const GAP_SECONDS = 300;

    /** Spans listed under the panel. */
    public const HISTORY_ROWS = 30;

    /** Never throws: a failed write must not cost the freezer its card button. */
    public function record(RemoteCardTerminal $terminal, TerminalStatus $status, ?CarbonInterface $at = null): void
    {
        try {
            $at = Carbon::instance($at ?? Carbon::now());
            $failed = array_key_exists('error', $status->raw);
            $online = $failed ? null : $status->online;
            $error = $failed ? mb_substr((string) $status->raw['error'], 0, 255) : null;
            $unitId = $terminal->card_terminal_unit_id === null ? null : (int) $terminal->card_terminal_unit_id;
            $vendId = $terminal->vend_id === null ? null : (int) $terminal->vend_id;

            DB::transaction(function () use ($terminal, $status, $at, $online, $error, $unitId, $vendId) {
                $last = Span::query()
                    ->where('remote_card_terminal_id', $terminal->id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if ($last
                    && $last->online === $online
                    && $this->sameId($last->card_terminal_unit_id, $unitId)
                    && $this->sameId($last->vend_id, $vendId)
                    && $at->getTimestamp() - $last->last_seen_at->getTimestamp() <= self::GAP_SECONDS) {
                    $last->forceFill([
                        'last_seen_at' => $at->greaterThan($last->last_seen_at) ? $at : $last->last_seen_at,
                        'last_state' => $status->state ?? $last->last_state,
                        'error' => $error ?? $last->error,
                        'checks' => $last->checks + 1,
                    ])->save();

                    return;
                }

                Span::create([
                    'remote_card_terminal_id' => $terminal->id,
                    'card_terminal_unit_id' => $unitId,
                    'vend_id' => $vendId,
                    'online' => $online,
                    'last_state' => $status->state,
                    'error' => $error,
                    'started_at' => $at,
                    'last_seen_at' => $at,
                    'checks' => 1,
                ]);
            });
        } catch (Throwable $e) {
            Log::warning('remote card terminal status log write failed', [
                'remote_card_terminal_id' => $terminal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Seconds online / offline / check failed / not checked over [$from, $to], with the drops (offline stretches
     * that began inside it) and the longest offline stretch. Uptime is over the CHECKED time only.
     *
     * @return array{online_seconds:int, offline_seconds:int, failed_seconds:int, unchecked_seconds:int,
     *               uptime_pct:float|null, drops:int, longest_offline_seconds:int}
     */
    public function summary(RemoteCardTerminal $terminal, CarbonInterface $from, CarbonInterface $to, ?CarbonInterface $now = null): array
    {
        $now = Carbon::instance($now ?? Carbon::now());
        $fromTs = $from->getTimestamp();
        $toTs = min($to->getTimestamp(), $now->getTimestamp());
        $totals = ['online' => 0, 'offline' => 0, 'check_failed' => 0];
        $drops = 0;
        $longestOffline = 0;

        $previous = null;
        foreach ($this->stretches($terminal, $from, $to, $now) as $s) {
            $start = max($fromTs, $s['start']);
            $end = min($toTs, $s['end']);
            $kind = $s['kind'];
            // A drop is going offline from anything else; offline again after an unchecked gap is the same drop.
            // A single offline answer followed by a gap still counts, though it covers no time.
            if ($kind === 'offline' && $previous !== 'offline' && $s['start'] >= $fromTs && $s['start'] <= $toTs) {
                $drops++;
            }
            $previous = $kind;
            if ($end <= $start) {
                continue;
            }
            $seconds = $end - $start;
            $totals[$kind] += $seconds;
            if ($kind === 'offline') {
                $longestOffline = max($longestOffline, $seconds);
            }
        }

        $checked = $totals['online'] + $totals['offline'];

        return [
            'online_seconds' => $totals['online'],
            'offline_seconds' => $totals['offline'],
            'failed_seconds' => $totals['check_failed'],
            'unchecked_seconds' => max(0, ($toTs - $fromTs) - array_sum($totals)),
            'uptime_pct' => $checked > 0 ? round($totals['online'] * 100 / $checked, 1) : null,
            'drops' => $drops,
            'longest_offline_seconds' => $longestOffline,
        ];
    }

    /**
     * The newest stretches, newest first, for the panel.
     *
     * @return list<array{kind:string, state:?string, error:?string, from:string, until:?string, seconds:int,
     *                    checks:int, open:bool, unchecked_after_seconds:int}>
     */
    public function history(RemoteCardTerminal $terminal, int $limit = self::HISTORY_ROWS, ?CarbonInterface $now = null): array
    {
        $now = Carbon::instance($now ?? Carbon::now());
        $spans = $this->spansOf($terminal)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $rows = [];
        foreach ($this->toStretches($spans, $now) as $s) {
            $rows[] = [
                'kind' => $s['kind'],
                'state' => $s['state'],
                'error' => $s['error'],
                'from' => Carbon::createFromTimestamp($s['start'])->toIso8601String(),
                'until' => $s['open'] ? null : Carbon::createFromTimestamp($s['end'])->toIso8601String(),
                'seconds' => $s['end'] - $s['start'],
                'checks' => $s['checks'],
                'open' => $s['open'],
                'unchecked_after_seconds' => $s['unchecked_after'],
            ];
        }

        return array_reverse($rows);
    }

    /** @return list<array{kind:string, state:?string, error:?string, start:int, end:int, checks:int, open:bool, unchecked_after:int}> */
    private function stretches(RemoteCardTerminal $terminal, CarbonInterface $from, CarbonInterface $to, Carbon $now): array
    {
        // The span running at $from started before it: take the last one that began before the window too.
        $before = $this->spansOf($terminal)
            ->where('started_at', '<', $from)
            ->orderByDesc('started_at')
            ->limit(1)
            ->get();
        $inside = $this->spansOf($terminal)
            ->where('started_at', '>=', $from)
            ->where('started_at', '<=', $to)
            ->orderBy('started_at')
            ->get();

        return $this->toStretches($before->concat($inside)->values(), $now);
    }

    /**
     * Each span runs from its start to the next span's start when the two checks are at most GAP_SECONDS apart
     * (the answer changed somewhere between them), else to its own last check: the rest was not checked. The
     * newest span runs to now while its last check is recent.
     *
     * @param  Collection<int, Span>  $spans  oldest first
     */
    private function toStretches(Collection $spans, Carbon $now): array
    {
        $out = [];
        $count = $spans->count();
        foreach ($spans as $i => $span) {
            $start = $span->started_at->getTimestamp();
            $seen = $span->last_seen_at->getTimestamp();
            $next = $i + 1 < $count ? $spans[$i + 1]->started_at->getTimestamp() : null;
            $open = false;
            if ($next !== null) {
                $end = $next - $seen <= self::GAP_SECONDS ? $next : $seen;
                $uncheckedAfter = $next - $end;
            } elseif ($now->getTimestamp() - $seen <= self::GAP_SECONDS) {
                $end = $now->getTimestamp();
                $open = true;
                $uncheckedAfter = 0;
            } else {
                $end = $seen;
                $uncheckedAfter = $now->getTimestamp() - $seen;
            }
            $out[] = [
                'kind' => $span->kind(),
                'state' => $span->last_state,
                'error' => $span->error,
                'start' => $start,
                'end' => max($start, $end),
                'checks' => $span->checks,
                'open' => $open,
                'unchecked_after' => max(0, $uncheckedAfter),
            ];
        }

        return $out;
    }

    /**
     * The terminal row's spans for the T05 it holds NOW: a swap edits the row in place, so a new T05 starts its
     * own history instead of inheriting the old one's drops.
     */
    private function spansOf(RemoteCardTerminal $terminal): \Illuminate\Database\Eloquent\Builder
    {
        return Span::query()
            ->where('remote_card_terminal_id', $terminal->id)
            ->when($terminal->card_terminal_unit_id !== null,
                fn ($q) => $q->where('card_terminal_unit_id', $terminal->card_terminal_unit_id));
    }

    private function sameId(mixed $a, ?int $b): bool
    {
        return ($a === null ? null : (int) $a) === $b;
    }
}
