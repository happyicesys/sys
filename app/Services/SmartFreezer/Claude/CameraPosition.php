<?php

namespace App\Services\SmartFreezer\Claude;

/**
 * How one freezer's camera was mounted at a session's time (Brian, 2026-11-14: "factor in whether the camera is
 * displayed inverted or at the normal angle"). `smart_freezer.claude_recognition.camera_positions.<vend code>` lists
 * them, each with:
 *  - `since`  when the camera took that position (null = from the start);
 *  - `camera` the camera it describes (3);
 *  - `rotate` 180 when the video comes upside down (lid at the bottom): every picture cut from it is turned upright
 *    first, so the crop boxes, the section map and Claude all see the freezer the same way up;
 *  - `baskets` where each basket sits in the upright picture (SectionMap), optional.
 * A freezer or time with no position is sent as filmed, with no map.
 */
final class CameraPosition
{
    /** @return array<string, mixed>|null the latest position whose `since` is not after the session */
    public static function for(?string $vendCode, ?string $at, int $camera): ?array
    {
        $positions = (array) (((array) config('smart_freezer.claude_recognition.camera_positions'))[(string) $vendCode] ?? []);

        return self::pick($positions, $at, $camera);
    }

    /**
     * @param  list<array<string, mixed>>  $positions
     * @return array<string, mixed>|null
     */
    public static function pick(array $positions, ?string $at, int $camera): ?array
    {
        $chosen = null;
        foreach ($positions as $position) {
            if ((int) ($position['camera'] ?? 3) !== $camera) {
                continue;
            }
            $since = (string) ($position['since'] ?? '');
            if ($since !== '' && $at !== null && $since > $at) {
                continue;
            }
            if ($chosen === null || (string) ($chosen['since'] ?? '') < $since) {
                $chosen = $position;
            }
        }

        return $chosen;
    }

    /** The ffmpeg filter that turns this position's picture upright ('' when it already is). */
    public static function uprightFilter(?array $position): string
    {
        return (int) ($position['rotate'] ?? 0) === 180 ? 'hflip,vflip,' : '';
    }
}
