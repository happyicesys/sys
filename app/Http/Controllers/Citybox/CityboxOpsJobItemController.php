<?php

namespace App\Http\Controllers\Citybox;

use App\Exceptions\CityboxApiException;
use App\Http\Controllers\Controller;
use App\Jobs\SubmitCityboxCount;
use App\Models\CityboxDoorOpenLog;
use App\Models\OpsJobItem;
use App\Models\Vend;
use App\Services\Citybox\RestockVisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Driver-facing chiller actions on an ops-job item (design §6b).
 *
 * Access is deliberately DRIVER-LEVEL — no new permission (Brian): the user
 * must be authenticated, the item must be visible to them (existing operator
 * scope on the query), and they must be the job's assigned driver OR hold
 * 'update operations'. Being on the job already proves they belong there.
 */
class CityboxOpsJobItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function openDoor(Request $request, int $id, RestockVisitService $visits): RedirectResponse
    {
        $item = $this->chillerItemOr403($request, $id);

        // A phone double-tap must not fire the door twice.
        $key = "citybox:open:{$item->id}";
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return redirect()->back()->withErrors(['citybox' => 'Door was just opened — wait a few seconds before opening again.']);
        }
        RateLimiter::hit($key, 20);

        try {
            $session = $visits->openDoor($item, $request->user(), $request->input('source', CityboxDoorOpenLog::SOURCE_OPS_JOB_PAGE), $request);
        } catch (CityboxApiException $e) {
            return redirect()->back()->withErrors(['citybox' => $this->friendly($e)]);
        }

        return redirect()->back()->with('success', 'Door opened — restock, then Stock In to push your count to CityBox. (session '.$session->msgId.')');
    }

    /** Manual retry of a failed / stuck stock submit (the amber banner's button). */
    public function retrySubmit(Request $request, int $id): RedirectResponse
    {
        $item = $this->chillerItemOr403($request, $id);
        if ((int) $item->status < (int) \App\Models\OpsJob::STATUS_DELIVERED) {
            return redirect()->back()->withErrors(['citybox' => 'Item is not Stocked-In yet — nothing to push.']);
        }
        $item->forceFill(['citybox_submit_status' => 'pending', 'citybox_submit_error' => null])->saveQuietly();
        SubmitCityboxCount::dispatch($item->id)->onQueue('high');

        return redirect()->back()->with('success', 'Re-pushing your count to CityBox…');
    }

    /** Door-open history for the item's vend (the collapsible list under the button). */
    public function doorOpens(Request $request, int $id): JsonResponse
    {
        $item = $this->chillerItemOr403($request, $id);
        $rows = CityboxDoorOpenLog::where('vend_id', $item->vend_id)->with('user:id,name')
            ->latest('requested_at')->limit(30)->get()
            ->map(fn ($l) => [
                'at' => $l->requested_at->format('Y-m-d H:i:s'), 'by' => $l->user?->name ?? '—',
                'source' => $l->source, 'result' => $l->result, 'message' => $l->citybox_message,
                'this_item' => $l->ops_job_item_id === $item->id,
            ]);

        return response()->json($rows);
    }

    private function chillerItemOr403(Request $request, int $id): OpsJobItem
    {
        $item = OpsJobItem::with(['vend', 'opsJob'])->findOrFail($id); // global scopes = operator visibility
        $vend = $item->vend;
        abort_unless($vend && $vend->machine_type === Vend::MACHINE_TYPE_SMART_CHILLER && $vend->citybox_equipment_id, 403, 'Not a CityBox Smart Chiller item.');

        $user = $request->user();
        $isDriver = $item->opsJob && (int) $item->opsJob->delivered_by === (int) $user->id;
        abort_unless($isDriver || $user->can('update operations'), 403, 'Only the assigned driver or an operations user can do this.');

        return $item;
    }

    private function friendly(CityboxApiException $e): string
    {
        $m = $e->getMessage();

        return match (true) {
            str_contains($m, '失联') => 'Chiller is offline — cannot open remotely. Check power/network at the site.',
            str_contains($m, 'BUSY'), str_contains($m, '使用中') => 'Door is already open or the chiller is in use — wait and try again.',
            str_contains($m, 'OPENING') => 'Door is already open.',
            default => 'Door open failed: '.$m,
        };
    }
}
