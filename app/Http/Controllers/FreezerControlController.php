<?php

namespace App\Http\Controllers;

use App\Models\FreezerControlCommand;
use App\Models\Vend;
use App\Services\Freezer\FreezerControlService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Setting/Edit > Smart Freezer > Remote controls.
 *
 * `show` is polled by the page (fast while a command is pending, slow otherwise); `store` sends one
 * command. Both are JSON: the page never reloads Inertia props for this panel.
 */
class FreezerControlController extends Controller
{
    /** Opening the door remotely is unmetered stock access, so it has its own permission. */
    public const DOOR_PERMISSION = 'update freezer-remote-door';

    public function __construct(private FreezerControlService $service) {}

    public function show(Request $request, Vend $vend): JsonResponse
    {
        abort_unless($vend->isSmartFreezer(), 404);

        $now = Carbon::now();
        $commands = FreezerControlCommand::where('vend_id', $vend->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $status = $vend->freezer_control_status_json;
        if (is_string($status)) {
            $status = json_decode($status, true);
        }
        $periodic = $vend->freezer_status_json;
        if (is_string($periodic)) {
            $periodic = json_decode($periodic, true);
        }

        return response()->json([
            'supported' => (int) $vend->apk_version_code >= FreezerControlService::MIN_APK_VERSION_CODE,
            'is_online' => (bool) $vend->is_online,
            'last_seen_at' => $vend->mqtt_last_updated_at?->toIso8601String(),
            'apk_version_code' => $vend->apk_version_code,
            'status' => $status,
            'status_at' => $vend->freezer_control_status_at ? Carbon::parse($vend->freezer_control_status_at)->toIso8601String() : null,
            'periodic_status_at' => $periodic['status_at'] ?? null,
            'can_control' => $request->user()->can('update machine-settings'),
            'can_door' => $request->user()->can(self::DOOR_PERMISSION),
            'setpoint' => ['min' => FreezerControlService::SETPOINT_MIN, 'max' => FreezerControlService::SETPOINT_MAX],
            'pending' => $commands->contains(fn ($c) => $c->displayStatus($now) === FreezerControlCommand::STATUS_PENDING),
            'commands' => $commands->map(fn (FreezerControlCommand $c) => [
                'id' => $c->id,
                'op' => $c->op,
                'args' => $c->args,
                'status' => $c->displayStatus($now),
                'message' => $c->response_msg,
                'requested_by' => $c->requested_by_name,
                'requested_at' => $c->created_at?->toIso8601String(),
                'responded_at' => $c->responded_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request, Vend $vend): JsonResponse
    {
        abort_unless($vend->isSmartFreezer(), 404);
        if (empty($vend->code)) {
            return response()->json(['message' => 'This machine has no code.'], 422);
        }

        $data = $request->validate([
            'op' => ['required', 'string', 'in:'.implode(',', FreezerControlService::OPS)],
            'args' => ['nullable', 'array'],
        ]);

        if (in_array($data['op'], ['lock', 'unlock'], true) && ! $request->user()->can(self::DOOR_PERMISSION)) {
            return response()->json(['message' => 'You do not have permission to lock or unlock the door remotely.'], 403);
        }

        if ((int) $vend->apk_version_code < FreezerControlService::MIN_APK_VERSION_CODE) {
            return response()->json(['message' => 'This machine\'s app is too old for remote controls.'], 422);
        }

        // One command at a time per machine: the device executes them serially anyway, and a
        // queue of taps behind a slow host is how a technician ends up double-actuating.
        $now = Carbon::now();
        $busy = FreezerControlCommand::where('vend_id', $vend->id)
            ->where('status', FreezerControlCommand::STATUS_PENDING)
            ->where('expires_at', '>', $now)
            ->exists();
        if ($busy) {
            return response()->json(['message' => 'Waiting for the machine to answer the previous command.'], 429);
        }

        $command = $this->service->dispatch($vend, $data['op'], $data['args'] ?? [], $request->user(), $request->ip());

        return response()->json([
            'id' => $command->id,
            'op' => $command->op,
            'status' => $command->status,
            'expires_at' => $command->expires_at->toIso8601String(),
        ], 202);
    }
}
