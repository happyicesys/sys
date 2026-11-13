<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\SmartFreezerRecognition;

/** The still frames Claude is shown for one door session. FfmpegDoorFrames in production. */
interface DoorFrameSource
{
    /**
     * @return array{frames: list<DoorFrame>, notes: list<string>} frames in camera, then time order; notes say
     *                                                             what was left out and why
     */
    public function framesFor(SmartFreezerRecognition $recognition): array;
}
