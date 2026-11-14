<?php

namespace App\Services\SmartFreezer\Claude;

/** One still picture from a door-session video, as sent to Claude. */
final class DoorFrame
{
    /** A frame of the whole picture, from the part of the video where something moves. */
    public const KIND_FRAME = 'frame';

    /** The baskets at full detail at the start of the video, before anything was taken. */
    public const KIND_BEFORE = 'before';

    /** The baskets at full detail at the end of the video, after the lid closed. */
    public const KIND_AFTER = 'after';

    /** A contact sheet: many small pictures close together over the part where a hand is in the freezer. */
    public const KIND_SHEET = 'sheet';

    /** A contact sheet of the lid opening only, where goods leave the freezer: what crosses it is taken. */
    public const KIND_EXIT = 'exit';

    public function __construct(
        public readonly int $camera,
        public readonly int $atMs,
        public readonly int $videoMs,
        public readonly string $jpeg,
        public readonly string $kind = self::KIND_FRAME,
        /** For a sheet: ['to_ms' => int, 'step_ms' => int, 'cols' => int, 'rows' => int]; atMs is its first picture. */
        public readonly array $sheet = [],
    ) {}

    public function isCloseUp(): bool
    {
        return $this->kind !== self::KIND_FRAME;
    }

    /** @return array<string, int|string> what the row keeps of it */
    public function meta(): array
    {
        return ['camera' => $this->camera, 'at_ms' => $this->atMs, 'video_ms' => $this->videoMs, 'bytes' => strlen($this->jpeg), 'kind' => $this->kind] + $this->sheet;
    }
}
