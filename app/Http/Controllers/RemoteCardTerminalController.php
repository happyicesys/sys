<?php

namespace App\Http\Controllers;

use App\Models\RemoteCardTerminal;
use App\Models\Vend;
use App\Services\CardTerminal\CardPaymentService;
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
    public function show(Vend $vend): JsonResponse
    {
        return response()->json($this->payload($vend));
    }

    /** Asks Payrallel for the terminal's state now (the service caches it briefly). */
    public function check(Vend $vend, CardPaymentService $payments): JsonResponse
    {
        $payments->terminalStatus($vend);

        return response()->json($this->payload($vend));
    }

    private function payload(Vend $vend): array
    {
        $terminal = RemoteCardTerminal::query()->with('unit')->where('vend_id', $vend->id)->first();

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
        ];
    }
}
