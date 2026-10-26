<?php

namespace App\Http\Controllers;

use App\Models\OpsJobItem;
use App\Services\Citybox\RestockPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Before/after restock photos on a smart machine's ops-job stop — a CityBox chiller (2026-10-13)
 * or a Smart Freezer (Brian, 2026-10-13: "store in local as a record in ops job"). The before
 * photo gates Open Door on both (checked in each door controller); only a chiller's photos are
 * also sent to CityBox (RestockPhotoService::pushIfReady).
 *
 * Driver-level access, the same rule as Open Door: the job's operator is the tenancy boundary,
 * and the user is the job's driver or holds 'update operations'.
 */
class RestockPhotoController extends Controller
{
    use Concerns\ScopesOpsJobToViewer;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request, int $id, RestockPhotoService $photos): JsonResponse
    {
        $item = $this->itemOr403($request, $id);
        $data = $request->validate([
            'slot' => ['required', 'in:'.implode(',', array_keys(RestockPhotoService::SLOTS))],
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif', 'max:20480'],
        ], ['file.mimetypes' => 'A restock photo must be a picture (JPEG, PNG, WebP or HEIC).']);

        $photos->store($item, $data['slot'], $request->file('file'));

        return response()->json($photos->payload($item));
    }

    public function destroy(Request $request, int $id, int $attachmentId, RestockPhotoService $photos): JsonResponse
    {
        $item = $this->itemOr403($request, $id);
        $photos->delete($item, $attachmentId);

        return response()->json($photos->payload($item));
    }

    private function itemOr403(Request $request, int $id): OpsJobItem
    {
        $item = OpsJobItem::with(['vend', 'opsJob'])->findOrFail($id);
        $this->assertWithinViewerCeiling($item->opsJob);
        abort_unless(RestockPhotoService::appliesTo($item->vend), 403, __('Restock photos are for Smart Chiller and Smart Freezer stops.'));

        $user = $request->user();
        $isDriver = $item->opsJob && (int) $item->opsJob->delivered_by === (int) $user->id;
        abort_unless($isDriver || $user->can('update operations'), 403, __('Only the assigned driver or an operations user can do this.'));

        return $item;
    }
}
