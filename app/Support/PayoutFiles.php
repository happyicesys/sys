<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bank payout files (CIMB refund + commission files, the PayNow CSV, the PayPal
 * worklist). They carry account numbers, so they are stored PRIVATE on DO
 * Spaces and only ever served through the authed download routes — never by
 * URL. Until 2026-10-09 they lived on the droplet's local disk, the only copy;
 * `payout-files:migrate-to-spaces` moved those, and reads still fall back to
 * 'local' for any file that was not moved.
 */
class PayoutFiles
{
    public static function disk(): string
    {
        $disk = config('refund.payout_files_disk', 'digitaloceanspaces');

        if ($disk === 'digitaloceanspaces'
            && (! config('filesystems.disks.digitaloceanspaces.key') || ! config('filesystems.disks.digitaloceanspaces.secret'))) {
            return 'local';
        }

        return $disk;
    }

    public static function put(string $path, string $content): void
    {
        if (! Storage::disk(self::disk())->put($path, $content, ['visibility' => 'private'])) {
            throw new \RuntimeException('Could not store payout file '.$path.' on '.self::disk().'.');
        }
    }

    /** The disk this file actually lives on, or null when it is on neither. */
    public static function locate(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        foreach (array_unique([self::disk(), 'local']) as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    public static function download(?string $path, string $filename): StreamedResponse
    {
        $disk = self::locate($path);
        abort_unless($disk, 404);

        return Storage::disk($disk)->download($path, $filename);
    }
}
