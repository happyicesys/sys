<?php

namespace App\Services\SmartFreezer\Claude;

/** One still picture from a door-session video, as sent to Claude. */
final class DoorFrame
{
    public function __construct(
        public readonly int $camera,
        public readonly int $atMs,
        public readonly int $videoMs,
        public readonly string $jpeg,
    ) {}

    /** @return array{camera: int, at_ms: int, video_ms: int, bytes: int} what the row keeps of it */
    public function meta(): array
    {
        return ['camera' => $this->camera, 'at_ms' => $this->atMs, 'video_ms' => $this->videoMs, 'bytes' => strlen($this->jpeg)];
    }
}
