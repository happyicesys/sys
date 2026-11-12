<?php

namespace App\Services\SmartFreezer;

use App\Models\CardPaymentIntent;
use App\Models\FreezerFailedSession;
use Carbon\Carbon;

/**
 * How a failed door went, for AI Recognition (Brian, 2026-11-12: "show how many attempts … how long … then
 * eventually the sorry page"): the door tries, the host's answers and the rescue's camera reads as the freezer
 * reported them (app 57+, `freezer_failed_sessions.door_log`), then the sorry page, plus the card hold's own
 * times from mark1.
 *
 * Two clocks, never mixed: the door steps are the MACHINE's, timed from its first door order ("+6.7 s"), and the
 * sorry page is on the same clock; card approved / hold released are mark1's. The machine runs a few seconds off
 * mark1 (50001 ~2 s ahead on 2026-11-12), so they are shown side by side, not sorted into one list.
 *
 * A report from an app before 57 has no timeline: the tries come from the app's own error line when it filed one
 * ("after N tries", none = one), and its `ended_at` is the sorry page (the report is written as the page appears).
 */
final class FreezerDoorTimeline
{
    /**
     * @param  ?int  $triesFromLog  the tries the app's `error:BoxVmc` line names (DoorFault::fromLog), null when it filed none
     * @return array{reported: bool, tries: ?int, waited: ?string, sorry_at: ?string, steps: list<array{offset: string, kind: string, text: string}>, card_approved_at: ?string, card_released_at: ?string}
     */
    public static function build(FreezerFailedSession $failed, ?CardPaymentIntent $card, ?int $triesFromLog): array
    {
        $log = is_array($failed->door_log) ? $failed->door_log : null;
        $steps = array_values(array_filter((array) ($log['steps'] ?? []), 'is_array'));
        $firstOrderMs = isset($log['first_order_at_ms']) ? (int) $log['first_order_at_ms'] : null;
        $shownMs = isset($log['error_shown_at_ms']) ? (int) $log['error_shown_at_ms'] : null;

        $orders = count(array_filter($steps, fn (array $s) => ($s['kind'] ?? null) === 'order'));
        $waitedMs = $firstOrderMs !== null && $shownMs !== null && $shownMs >= $firstOrderMs ? $shownMs - $firstOrderMs : null;

        $lines = array_map(fn (array $s) => [
            'offset' => self::offset((int) ($s['t_ms'] ?? 0)),
            'kind' => (string) ($s['kind'] ?? ''),
            'text' => self::words($s),
        ], $steps);
        if ($waitedMs !== null) {
            $lines[] = ['offset' => self::offset($waitedMs), 'kind' => 'sorry', 'text' => __('Sorry page shown')];
        }

        $sorryAt = $shownMs !== null
            ? Carbon::createFromTimestampMs($shownMs, config('app.timezone'))->format('H:i:s')
            : $failed->ended_at?->timezone(config('app.timezone'))->format('H:i:s');

        return [
            'reported' => $log !== null && $steps !== [],
            'tries' => $orders > 0 ? $orders : $triesFromLog,
            'waited' => $waitedMs !== null ? self::seconds($waitedMs) : null,
            'sorry_at' => $sorryAt,
            'steps' => $lines,
            'card_approved_at' => $card?->approved_at?->timezone(config('app.timezone'))->format('H:i:s'),
            'card_released_at' => $card !== null && $card->state === CardPaymentIntent::STATE_VOIDED
                ? $card->voided_at?->timezone(config('app.timezone'))->format('H:i:s')
                : null,
        ];
    }

    /** One machine step in a person's words; the host's own words are kept as they came. */
    private static function words(array $step): string
    {
        $try = (int) ($step['try'] ?? 0);
        $text = trim((string) ($step['text'] ?? ''));

        return match ($step['kind'] ?? null) {
            'order' => __('Try :n: door order sent to the host', ['n' => $try]),
            'opened' => __('Try :n: door opened', ['n' => $try]).($text !== '' ? " ({$text})" : ''),
            'refused' => __('Try :n refused: :text', ['n' => $try, 'text' => $text]),
            'not_delivered' => __('Try :n never reached the host: :text', ['n' => $try, 'text' => $text]),
            'no_answer' => __('Try :n: no answer from the host (:text)', ['n' => $try, 'text' => $text]),
            'check' => __('Still shut, cameras checked: :text', ['text' => $text]),
            'unlock' => __('Lock opened directly: :text', ['text' => $text]),
            'unlock_failed' => __('Direct unlock failed: :text', ['text' => $text]),
            'gave_up' => __('Gave up: :text', ['text' => $text]),
            default => $text,
        };
    }

    /** "+6.7 s" from the first door order. */
    private static function offset(int $ms): string
    {
        return '+'.self::seconds($ms);
    }

    /** "6.7 s". */
    private static function seconds(int $ms): string
    {
        return number_format($ms / 1000, 1).' s';
    }
}
