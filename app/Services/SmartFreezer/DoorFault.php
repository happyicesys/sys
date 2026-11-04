<?php

namespace App\Services\SmartFreezer;

/**
 * Why a freezer's door failed, read from the line the app files as `error:BoxVmc`:
 * "Door refused for SF-…: LOCK (1号门锁开锁超时…)" or "Door call never reached host for SF-…: THREW".
 *
 * The codes are the host's `faultType`. Zijia has not defined them all: lock, business, storage and power
 * come from their documents and field logs, the rest are read from the name alone
 * (apk/smart-freezer/MACHINE_STATUS_AUDIT_2026-09-10.md).
 */
final class DoorFault
{
    public const NOT_DELIVERED = 'NOT_DELIVERED';

    public const CODES = ['LOCK', 'DOOR', 'CAMERA', 'STORAGE', 'POWER', 'MACHINE', 'BUSINESS', 'SHOPLOCK', 'EXCEPTION', 'UNKNOWN', self::NOT_DELIVERED, 'DOOR_OPEN_FAILED', 'DOOR_OPEN_TIMEOUT', 'UNLOCK_AUTHORIZATION_FAILED'];

    /** @return array{session_ref: string, code: string, text: string}|null */
    public static function fromLog(string $line): ?array
    {
        if (preg_match('/^Door refused for (\S+): (\S+) \((.*)\)$/s', $line, $m)) {
            return ['session_ref' => $m[1], 'code' => $m[2], 'text' => $m[3]];
        }
        if (preg_match('/^Door call never reached host for (\S+): (.*)$/s', $line, $m)) {
            return ['session_ref' => $m[1], 'code' => self::NOT_DELIVERED, 'text' => $m[2]];
        }

        return null;
    }

    /**
     * From a failed session's report: `detail` carries the machine's words when the app knew them
     * ("door refused: LOCK 1号门锁…", app 48+), else the app's own fault code is all there is.
     *
     * @return array{code: string, text: string}
     */
    public static function fromReport(string $reason, ?string $detail): array
    {
        if (preg_match('/^door refused: (\S+) ?(.*)$/s', (string) $detail, $m)) {
            return ['code' => $m[1], 'text' => $m[2]];
        }
        if (preg_match('/^door not delivered: (.*)$/s', (string) $detail, $m)) {
            return ['code' => self::NOT_DELIVERED, 'text' => $m[1]];
        }

        return ['code' => strtoupper($reason), 'text' => (string) $detail];
    }

    public static function plain(string $code): string
    {
        return match ($code) {
            'LOCK' => __('The lock did not open: a timeout, or a stuck or damaged bolt. The machine\'s own words follow the code.'),
            'DOOR' => __('The controller reported a problem with the door.'),
            'CAMERA' => __('The controller reported a problem with a camera, so it would not open the door.'),
            'STORAGE' => __('The controller is low on storage for its videos, so it would not open the door.'),
            'POWER' => __('The controller reported a mains power problem.'),
            'MACHINE' => __('The controller reported a problem with the machine itself.'),
            'BUSINESS' => __('The controller would not sell: not activated, upgrading, or no network to Zijia.'),
            'SHOPLOCK' => __('The controller still held another shopping session on this door.'),
            'EXCEPTION' => __('The controller hit an unexpected error.'),
            self::NOT_DELIVERED => __('The app\'s open-door command never reached the controller.'),
            'DOOR_OPEN_FAILED' => __('The door did not open. Older app versions do not say which part failed.'),
            'DOOR_OPEN_TIMEOUT' => __('The door did not answer in time.'),
            'UNLOCK_AUTHORIZATION_FAILED' => __('The app did not get the go-ahead to unlock, so the door was never told to open.'),
            default => __('The sale stopped on this error. Read the words after the code.'),
        };
    }

    /** @return list<array{code: string, plain: string}> */
    public static function legend(): array
    {
        return array_map(fn (string $code) => ['code' => $code, 'plain' => self::plain($code)], self::CODES);
    }
}
