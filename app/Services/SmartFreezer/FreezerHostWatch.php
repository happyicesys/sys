<?php

namespace App\Services\SmartFreezer;

use App\Models\CardPaymentIntent;
use App\Models\Vend;
use App\Services\AlertEmailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Two alarms nobody raised on 2026-11-22 (50001, `apk/smart-freezer/INCIDENT_50001_2026-11-22.md`): the freezer stayed
 * online but its app lost the Zijia host (door, cameras, thermostat) for over an hour, and a T05 card capture came in
 * with no sale record after it. Brian found it from the "no AI session" box an hour later.
 *
 *  - **dark**: the machine is online (any frame in the last {@see ONLINE_MINUTES}) but its newest FREEZERSTATUS frames
 *    carry no host data (no `locks`), since {@see DARK_AFTER_MINUTES} or more. The app sends a status frame every
 *    15 minutes, so this is noticed 10–25 minutes after it starts.
 *  - **no_trade**: a T05 hold whose door closed over {@see NO_TRADE_AFTER_MINUTES} ago (capture sent) has no sale
 *    carrying its session ref (`SFREF`). A normal sale's TRADE arrives within a second of the capture.
 *
 * Each goes once per episode to the operator's machine alert recipients
 * ({@see AlertEmailService::sendVendOperationErrorNotificationMail}); a dark freezer alerts again only after it
 * reported host data once more. Read-only apart from the cache keys and the alert's own log row.
 */
class FreezerHostWatch
{
    public const DARK_AFTER_MINUTES = 10;

    public const ONLINE_MINUTES = 5;

    public const NO_TRADE_AFTER_MINUTES = 3;

    /** How far back a missing sale is still looked for (a later TRADE replay clears it on its own). */
    public const NO_TRADE_LOOKBACK_HOURS = 6;

    public const TYPE_DARK = 'freezer_host_dark';

    public const TYPE_NO_TRADE = 'freezer_card_no_sale';

    public function __construct(private readonly AlertEmailService $alerts) {}

    /** @return array{dark: list<int>, no_trade: list<string>} machine codes gone dark, session refs with no sale */
    public function run(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $out = ['dark' => [], 'no_trade' => []];
        $freezers = Vend::withoutGlobalScopes()->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)
            ->where('is_active', true)->where('is_disposed', false)->get(['id', 'code', 'operator_id', 'machine_type']);

        foreach ($freezers as $vend) {
            $since = $this->darkSince($vend, $now);
            $key = 'freezer-host-dark:'.$vend->id;
            if ($since === null) {
                Cache::forget($key);

                continue;
            }
            if ($since->gt($now->copy()->subMinutes(self::DARK_AFTER_MINUTES)) || ! Cache::add($key, $since->toIso8601String(), now()->addDays(2))) {
                continue;
            }
            $out['dark'][] = (int) $vend->code;
            $this->alerts->sendVendOperationErrorNotificationMail(Vend::withoutGlobalScopes()->findOrFail($vend->id), self::TYPE_DARK,
                sprintf('no door/camera data since %s, machine online', $since->format('H:i')));
            Log::warning('freezer host dark', ['vend' => $vend->code, 'since' => $since->toDateTimeString()]);
        }

        $intents = CardPaymentIntent::query()
            ->whereIn('vend_id', $freezers->pluck('id'))
            ->whereNotNull('session_ref')->whereNotNull('door_closed_at')
            ->whereBetween('door_closed_at', [$now->copy()->subHours(self::NO_TRADE_LOOKBACK_HOURS), $now->copy()->subMinutes(self::NO_TRADE_AFTER_MINUTES)])
            ->whereIn('state', [CardPaymentIntent::STATE_AWAITING_AI, CardPaymentIntent::STATE_CAPTURING, CardPaymentIntent::STATE_CAPTURED])
            ->get(['id', 'vend_id', 'session_ref', 'amount_cents', 'door_closed_at', 'created_at']);

        foreach ($intents as $intent) {
            $hasSale = DB::table('vend_transactions')->where('vend_id', $intent->vend_id)
                ->where('transaction_datetime', '>=', $intent->created_at->copy()->subHours(2))
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(vend_transaction_json, '$.SFREF')) = ?", [$intent->session_ref])
                ->exists();
            if ($hasSale || ! Cache::add('freezer-no-trade:'.$intent->id, 1, now()->addDays(7))) {
                continue;
            }
            $vend = $freezers->firstWhere('id', $intent->vend_id);
            $out['no_trade'][] = (string) $intent->session_ref;
            $this->alerts->sendVendOperationErrorNotificationMail(Vend::withoutGlobalScopes()->findOrFail($vend->id), self::TYPE_NO_TRADE, sprintf(
                'card $%s held for %s, door closed %s, no sale record',
                number_format($intent->amount_cents / 100, 2), $intent->session_ref, $intent->door_closed_at->format('H:i')));
            Log::warning('freezer card capture with no sale', ['vend' => $vend->code, 'intent' => $intent->id, 'session' => $intent->session_ref]);
        }

        return $out;
    }

    /** When this freezer's status frames lost the host, or null while it is fine, offline or has no recent status. */
    public function darkSince(Vend $vend, Carbon $now): ?Carbon
    {
        $online = DB::table('vend_data')->where('vend_code', $vend->code)
            ->where('created_at', '>=', $now->copy()->subMinutes(self::ONLINE_MINUTES))->exists();
        if (! $online) {
            return null;
        }
        $statuses = DB::table('vend_data')->where('vend_code', $vend->code)->where('type', 'FREEZERSTATUS')
            ->where('created_at', '>=', $now->copy()->subHours(6))
            ->orderByDesc('id')->limit(40)->get(['value', 'created_at']);
        $since = null;
        foreach ($statuses as $row) {
            if (self::hasHostData($row->value)) {
                break;
            }
            $since = Carbon::parse($row->created_at);
        }

        return $since;
    }

    /** A status frame (vend_data.value) that carries the host's lock reading. */
    public static function hasHostData(?string $value): bool
    {
        $frame = json_decode((string) $value, true);
        $status = json_decode((string) base64_decode((string) ($frame['p'] ?? ''), true), true);

        return is_array($status) && isset($status['locks']);
    }
}
