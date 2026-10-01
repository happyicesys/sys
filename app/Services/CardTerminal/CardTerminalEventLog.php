<?php

namespace App\Services\CardTerminal;

use App\Models\CardPaymentEvent;
use App\Models\RemoteCardTerminal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The remote-terminal timeline's only writer: one card_payment_events row and
 * one `payrallel` log line per event. Never throws — losing a log line must
 * never fail a payment.
 *
 * Events (the `event` column):
 *   provider.http         one Payrallel call: path, HTTP status, raw body, ms
 *   provider.unreachable  connection failure / timeout, ms
 *   intent.created        a device attempt opened (reference, amount, mode)
 *   intent.state          state change, from → to, provider status, error
 *   terminal.status       terminal online/state changed (or first seen)
 *   terminal.bound / terminal.deactivated   payrallel:bind-terminal
 *   device.rejected       a device request refused before any money logic
 *   device.request        a device action (authorize / cancel / capture / void)
 */
class CardTerminalEventLog
{
    /** Raw provider bodies are kept whole up to this size; longer ones are cut. */
    public const BODY_LIMIT = 4000;

    public function record(
        string $event,
        array $detail = [],
        ?RemoteCardTerminal $terminal = null,
        ?string $orderId = null,
        ?int $durationMs = null,
        string $level = 'info',
        ?int $vendId = null,
    ): void {
        $vendId ??= $terminal?->vend_id;
        try {
            CardPaymentEvent::create([
                'vend_id' => $vendId,
                'remote_card_terminal_id' => $terminal?->id,
                'custom_order_id' => $orderId,
                'event' => $event,
                'level' => $level,
                'detail' => $detail ?: null,
                'duration_ms' => $durationMs,
                'created_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('card_payment_events write failed', ['event' => $event, 'error' => $e->getMessage()]);
        }

        try {
            Log::channel('payrallel')->log($level, $event, array_filter([
                'vend_id' => $vendId,
                'terminal_id' => $terminal?->id,
                'order' => $orderId,
                'ms' => $durationMs,
            ], fn ($v) => $v !== null) + $detail);
        } catch (Throwable) {
            // The DB row is the record; a full disk must not break a payment.
        }
    }

    /** A provider body, cut to BODY_LIMIT, as a string for the detail column. */
    public static function body(string $raw): string
    {
        return mb_strlen($raw) > self::BODY_LIMIT ? mb_substr($raw, 0, self::BODY_LIMIT).'…[cut]' : $raw;
    }
}
