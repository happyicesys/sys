<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\SmartFreezerClip;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plays one of our own freezer door-session recordings (`smart_freezer_clips`).
 *
 * The file is private on Spaces and shows customers, so it is reached only through here: the AI
 * Recognition permission plus the viewer's operator boundary (the table has no global scope).
 * On Spaces the browser is sent a short-lived signed link, so seeking is served by Spaces itself
 * (Range requests) instead of streaming megabytes through PHP.
 */
class FreezerClipController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read ai-recognition']);
    }

    public function show(SmartFreezerClip $clip): Response
    {
        $operatorId = OperatorVendFilterScope::viewerOperatorId();
        abort_if($operatorId !== null && (int) $clip->vend?->operator_id !== (int) $operatorId, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($clip->path), 404);

        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl(
                $clip->path,
                now()->addMinutes((int) config('smart_freezer.clips.link_minutes')),
                ['ResponseContentType' => 'video/mp4'],
            ));
        }

        return $disk->response($clip->path, basename($clip->path), [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'private, max-age=600',
        ], 'inline');
    }
}
