<?php

namespace App\Services\SmartFreezer;

use App\Models\Vend;

/**
 * Which cameras a smart freezer must see online before it sells (Brian, 2026-10-16): the ones Zijia's
 * AI judges a door session from. The freezer asks its host when the customer picks a payment method
 * and again after payment, before the door unlocks; a camera down stops the sale. Read from
 * config('smart_freezer.camera_guard') and served as `guard_cameras` on clips/settings.
 */
final class FreezerCameraGuard
{
    /** @return list<int> camera ids to check on this machine; empty = no check. */
    public static function camerasFor(Vend $vend): array
    {
        if (! $vend->isSmartFreezer()) {
            return [];
        }
        $default = self::ids((array) config('smart_freezer.camera_guard.cameras'));
        foreach ((array) config('smart_freezer.camera_guard.vends') as $entry) {
            [$code, $override] = array_pad(explode(':', (string) $entry, 2), 2, null);
            if (trim((string) $code) !== (string) $vend->code) {
                continue;
            }

            return $override !== null && trim($override) !== '' ? self::ids(explode('+', $override)) : $default;
        }

        return [];
    }

    /** @return list<int> distinct, ascending, within the host's camera range */
    private static function ids(array $raw): array
    {
        $ids = array_filter(array_map('intval', $raw), fn (int $id) => $id >= 1 && $id <= FreezerClipStore::MAX_CAMERA_ID);
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
