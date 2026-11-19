<?php

namespace App\Http\Controllers;

use App\Models\RemoteCardTerminal;
use App\Models\Vend;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\TerminalStatusLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * Setting > Edit > "Remote card terminal (T05 · Payrallel)": what the freezer's card rail is
 * bound to, and whether that T05 is online. Read-only — a T05 is a Data Management > Card
 * Terminal unit (SN + access token) and is bound with the Card Terminal picker on the same
 * page (CardTerminalBindingService → RemoteCardTerminalBinder). The token never leaves the
 * server.
 */
class RemoteCardTerminalController extends Controller
{
    public function __construct(private readonly TerminalStatusLog $statusLog) {}

    public function show(Vend $vend): JsonResponse
    {
        return response()->json($this->payload($vend));
    }

    /** Asks Payrallel for the terminal's state now (the service caches it briefly), link on or off. */
    public function check(Vend $vend, CardPaymentService $payments): JsonResponse
    {
        $terminal = RemoteCardTerminal::query()->where('vend_id', $vend->id)->first();
        if ($terminal) {
            $payments->statusOf($terminal);
        }

        return response()->json($this->payload($vend));
    }

    private function payload(Vend $vend): array
    {
        $terminal = RemoteCardTerminal::query()->with('unit')->where('vend_id', $vend->id)->first();
        $now = Carbon::now();

        return [
            'supported' => $vend->isSmartFreezer(),
            'terminal' => $terminal ? [
                'provider' => $terminal->provider,
                'sn' => $terminal->unit?->terminal_id,
                'label' => $terminal->label,
                // Bound by the old command line rather than from a Card Terminal unit.
                'from_command' => $terminal->card_terminal_unit_id === null,
                'is_active' => (bool) $terminal->is_active,
                'last_online' => $terminal->last_online,
                'last_state' => $terminal->last_state,
                'last_status_at' => $terminal->last_status_at?->toIso8601String(),
                'updated_at' => $terminal->updated_at?->toIso8601String(),
            ] : null,
            // Online / offline log (TerminalStatusLog): last 24 h, last 7 days, the newest stretches.
            'history' => $terminal ? [
                'day' => $this->statusLog->summary($terminal, $now->copy()->subDay(), $now, $now),
                'week' => $this->statusLog->summary($terminal, $now->copy()->subDays(7), $now, $now),
                'spans' => $this->statusLog->history($terminal, TerminalStatusLog::HISTORY_ROWS, $now),
            ] : null,
        ];
    }
}
