<?php

namespace App\Http\Controllers;

use App\Models\RemoteCardTerminal;
use App\Models\Vend;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\RemoteCardTerminalBinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Setting > Edit > "Remote card terminal (T05)": binds a Payrallel terminal to a smart
 * freezer, or deactivates it, without SSH. Same rules as `php artisan
 * payrallel:bind-terminal` (both go through RemoteCardTerminalBinder); the freezer
 * (app v25+) follows within 5 minutes.
 *
 * The access token is write-only: stored encrypted, never sent back to the browser — the
 * panel only learns whether one is set. Not a NETS unit: those live in
 * card_terminal_units and are reconciled from the NETS settlement report, which a
 * Payrallel terminal never appears in (see RemoteCardTerminal).
 */
class RemoteCardTerminalController extends Controller
{
    public function show(Vend $vend): JsonResponse
    {
        return response()->json($this->payload($vend));
    }

    public function update(Request $request, Vend $vend, RemoteCardTerminalBinder $binder, CardPaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:64'],
            'access_token' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $result = $binder->save(
            $vend,
            (bool) $data['is_active'],
            $data['label'] ?? null,
            $data['access_token'] ?? null,
            $request->user()?->name ?? 'mark1',
            'setting-edit',
        );

        if ($result['terminal']->is_active) {
            $payments->terminalStatus($vend); // refreshes last_online / last_state for the reply
        }

        return response()->json($this->payload($vend) + ['released_from' => $result['released_from']]);
    }

    /** Asks Payrallel for the terminal's state now (the service caches it briefly). */
    public function check(Vend $vend, CardPaymentService $payments): JsonResponse
    {
        $payments->terminalStatus($vend);

        return response()->json($this->payload($vend));
    }

    private function payload(Vend $vend): array
    {
        $terminal = RemoteCardTerminal::query()->where('vend_id', $vend->id)->first();

        return [
            'supported' => $vend->isSmartFreezer(),
            'terminal' => $terminal ? [
                'provider' => $terminal->provider,
                'label' => $terminal->label,
                'is_active' => (bool) $terminal->is_active,
                'has_token' => filled($terminal->getRawOriginal('access_token')),
                'last_online' => $terminal->last_online,
                'last_state' => $terminal->last_state,
                'last_status_at' => $terminal->last_status_at?->toIso8601String(),
                'updated_at' => $terminal->updated_at?->toIso8601String(),
            ] : null,
        ];
    }
}
