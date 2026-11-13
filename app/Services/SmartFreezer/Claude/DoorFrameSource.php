<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\SmartFreezerRecognition;

/** The still frames Claude is shown for one door session. FfmpegDoorFrames in production. */
interface DoorFrameSource
{
    /**
     * @param  int|null  $framesPerSession  motion frames for the whole session; null = `frames_per_session`
     * @return array{frames: list<DoorFrame>, notes: list<string>} frames in camera, then time order, each camera's
     *                                                             before/after close-ups after its frames; notes say
     *                                                             what was left out and why
     */
    public function framesFor(SmartFreezerRecognition $recognition, ?int $framesPerSession = null): array;
}
