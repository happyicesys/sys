<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\SmartFreezerVideo;
use App\Services\SmartFreezer\ZijiaVideoCopier;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plays one Zijia door-session video from AI Recognition (Brian, 2026-11-09): their link while it still
 * answers, else our own private copy (a short-lived signed Spaces link), else a plain "no longer
 * available". The AI Recognition permission and the viewer's operator boundary, as for our clips.
 */
class ZijiaVideoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read ai-recognition']);
    }

    public function show(SmartFreezerVideo $video, int $index, ZijiaVideoCopier $copier): Response
    {
        $operatorId = OperatorVendFilterScope::viewerOperatorId();
        abort_if($operatorId !== null && (int) $video->vend?->operator_id !== (int) $operatorId, 404);

        $url = ((array) $video->video_urls)[$index] ?? null;
        abort_unless(is_string($url) && $url !== '', 404);

        $play = $copier->playUrl($url);
        abort_if($play === null, 404, __('This video is no longer available from Zijia, and we hold no copy of it.'));

        return redirect()->away($play);
    }
}
