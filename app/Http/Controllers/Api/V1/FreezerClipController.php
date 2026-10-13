<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\Vend;
use App\Services\SmartFreezer\FreezerClipStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Device → mark1 door-session recordings (camera 5 today):
 *
 *   GET  /api/v1/vends/{code}/clips/settings                 the switch: which camera, piece/max length
 *   POST /api/v1/vends/{code}/clips/{sessionRef}/{camera}   body = the mp4 bytes (video/mp4)
 *
 * Signed by the machine (VerifyDeviceSignature: the signature covers the path and the whole body,
 * so the session and camera cannot be swapped onto someone else's file). What the freezer knows
 * about the recording rides in headers — device clock, epoch ms:
 *   X-Clip-Started-At, X-Clip-Door-Closed-At, X-Clip-Duration-Ms, X-Clip-Meta (small JSON object).
 *
 * 201 stored (a re-upload replaces it); 422 refused for good — the freezer drops it rather than
 * retrying forever. FreezerClipStore holds the rules.
 */
class FreezerClipController extends Controller
{
    /**
     * What the freezer should record, read at boot and every 30 min (and cached on the device):
     * `{"camera": 5|null, "segment_ms": 10000, "max_ms": 300000}`. `camera` null = do not record —
     * the off switch that needs no OTA (config smart_freezer.clips.camera / .vends).
     */
    public function settings(Request $request): JsonResponse
    {
        /** @var Vend $vend */
        $vend = $request->attributes->get(VerifyDeviceSignature::VEND_ATTRIBUTE);
        // FREEZER_CLIP_VENDS entries are "50001" (the default camera) or "2009:3" (that camera on
        // that machine — a bench unit without a camera 5).
        $camera = (int) config('smart_freezer.clips.camera');
        $only = [];
        foreach ((array) config('smart_freezer.clips.vends') as $entry) {
            [$code, $override] = array_pad(explode(':', (string) $entry, 2), 2, null);
            $only[trim($code)] = $override !== null && $override !== '' ? (int) $override : null;
        }
        if (array_key_exists((string) $vend->code, $only) && $only[(string) $vend->code] !== null) {
            $camera = $only[(string) $vend->code];
        }
        $on = $vend->isSmartFreezer() && $camera > 0 && $camera <= FreezerClipStore::MAX_CAMERA_ID
            && ($only === [] || array_key_exists((string) $vend->code, $only));

        return response()->json([
            'camera' => $on ? $camera : null,
            'segment_ms' => (int) config('smart_freezer.clips.segment_ms'),
            'max_ms' => (int) config('smart_freezer.clips.max_ms'),
        ]);
    }

    public function store(Request $request, string $code, string $sessionRef, int $camera, FreezerClipStore $store): JsonResponse
    {
        /** @var Vend $vend */
        $vend = $request->attributes->get(VerifyDeviceSignature::VEND_ATTRIBUTE);
        if (! $vend->isSmartFreezer()) {
            return response()->json(['error' => 'not a smart freezer'], 422);
        }

        $meta = json_decode((string) $request->header('X-Clip-Meta', ''), true);
        try {
            $clip = $store->store($vend, $sessionRef, $camera, $request->getContent(), [
                'recorded_from_ms' => $request->header('X-Clip-Started-At'),
                'door_closed_ms' => $request->header('X-Clip-Door-Closed-At'),
                'duration_ms' => $request->header('X-Clip-Duration-Ms'),
                'meta' => is_array($meta) && ! array_is_list($meta) ? array_slice($meta, 0, 20, true) : [],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $clip->id, 'bytes' => $clip->bytes, 'duration_ms' => $clip->duration_ms,
        ], 201);
    }
}
