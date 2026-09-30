<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\FreezerControlCommand;
use App\Models\OpsJobItem;
use App\Models\Vend;
use App\Services\Freezer\FreezerControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Driver-facing Smart Freezer door on an ops-job item: the freezer counterpart of
 * CityboxOpsJobItemController::openDoor.
 *
 * Access is DRIVER-LEVEL, the same rule as the chiller: the item must be visible to the user
 * (operator scope on the query) and they must be the job's assigned driver OR hold
 * 'update operations'. The Setting/Edit remote panel keeps its own 'update freezer-remote-door'
 * permission for opening a freezer outside a job.
 *
 * The door is the existing FREEZERCTL `unlock` op. The device answers asynchronously (and refuses
 * with `busy` while a customer sale owns the cabinet), so openDoor returns the command id and the
 * button polls doorStatus for the answer.
 */
class FreezerOpsJobItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function openDoor(Request $request, int $id, FreezerControlService $controls): JsonResponse
    {
        $item = $this->freezerItemOr403($request, $id);
        $vend = $item->vend;

        if ((int) $vend->apk_version_code < FreezerControlService::minApkVersionFor('unlock')) {
            return response()->json(['message' => 'This freezer\'s app is too old to open the door remotely.'], 422);
        }

        // A phone double-tap must not fire the door twice.
        $key = "freezer:open:{$item->id}";
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['message' => 'Door was just opened — wait a few seconds before opening again.'], 429);
        }
        RateLimiter::hit($key, 20);

        $command = $controls->dispatch(
            $vend, 'unlock', [], $request->user(), $request->ip(), FreezerControlCommand::SOURCE_OPS_JOB,
        );

        return response()->json($this->present($command));
    }

    public function doorStatus(Request $request, int $id, string $cmdId): JsonResponse
    {
        $item = $this->freezerItemOr403($request, $id);
        $command = FreezerControlCommand::query()
            ->where('vend_id', $item->vend_id)
            ->where('cmd_id', $cmdId)
            ->where('op', 'unlock')
            ->firstOrFail();

        return response()->json($this->present($command));
    }

    /** @return array{cmd_id: string, status: string, message: ?string} */
    private function present(FreezerControlCommand $command): array
    {
        return [
            'cmd_id' => $command->cmd_id,
            'status' => $command->displayStatus(Carbon::now()),
            'message' => $command->response_msg,
        ];
    }

    private function freezerItemOr403(Request $request, int $id): OpsJobItem
    {
        $item = OpsJobItem::with(['vend', 'opsJob'])->findOrFail($id); // global scopes = operator visibility
        abort_unless($item->vend && $item->vend->machine_type === Vend::MACHINE_TYPE_SMART_FREEZER, 403, 'Not a Smart Freezer item.');

        $user = $request->user();
        $isDriver = $item->opsJob && (int) $item->opsJob->delivered_by === (int) $user->id;
        abort_unless($isDriver || $user->can('update operations'), 403, 'Only the assigned driver or an operations user can do this.');

        return $item;
    }
}
