<?php

namespace App\Services\Freezer;

use App\Models\FreezerControlCommand;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * A small copy of a camera still, for the places that draw it postage-stamp sized.
 *
 * The cabinet uploads a 1280×720 JPEG — 35-140 KB measured on 50001 — and the panel drew every
 * tile and every history thumbnail from that full file: one Setting/Edit view pulled ~300 KB of
 * image to paint frames 112 px wide. A 320 px WebP of the same frame is ~8 KB, so the card paints
 * from a tenth of the bytes and the big view still opens the original.
 *
 * Generated when the photo lands, and on demand for the photos that arrived before this existed;
 * the path is remembered on the command so neither the panel nor this class has to ask the object
 * store whether it is there. Failure is never fatal — the caller falls back to the full image.
 */
class FreezerPhotoThumbnail
{
    /** Wide enough for a retina tile at the sizes the panel uses, small enough to stay ~8 KB. */
    public const WIDTH = 320;

    /** WebP at this quality measured 8 KB against 140 KB of source, with no visible loss at tile size. */
    private const QUALITY = 70;

    /**
     * The stored thumbnail for this photo, making it first if need be. Null when there is no photo,
     * or when the image could not be read — the caller then serves the original.
     */
    public function pathFor(FreezerControlCommand $command): ?string
    {
        if ($command->attachment_thumb_path && Storage::exists($command->attachment_thumb_path)) {
            return $command->attachment_thumb_path;
        }
        if (! $command->attachment_path || ! Storage::exists($command->attachment_path)) {
            return null;
        }

        return $this->generate($command, Storage::get($command->attachment_path));
    }

    /**
     * Make the thumbnail from bytes already in hand — the upload path, where re-reading the object
     * store for a file we just wrote would be a needless round trip.
     */
    public function generate(FreezerControlCommand $command, string $jpeg): ?string
    {
        $path = $this->thumbPath($command->attachment_path);
        if ($path === null) {
            return null;
        }

        try {
            $thumb = Image::read($jpeg)->scaleDown(width: self::WIDTH)->encode(new WebpEncoder(quality: self::QUALITY));
            Storage::put($path, (string) $thumb, 'private');
        } catch (Throwable $e) {
            Log::warning('Freezer photo thumbnail failed', ['command' => $command->id, 'error' => $e->getMessage()]);

            return null;
        }

        // Remembering where it went is an optimisation, not the product: if the column is not there
        // yet (a deploy that has landed ahead of its migration), the file is still served, just
        // made again next time.
        try {
            $command->forceFill(['attachment_thumb_path' => $path])->saveQuietly();
        } catch (Throwable $e) {
            Log::warning('Freezer photo thumbnail path not stored', ['command' => $command->id, 'error' => $e->getMessage()]);
        }

        return $path;
    }

    /** `…-cam4.jpg` → `…-cam4-thumb.webp`, beside the original so the two are deleted together. */
    private function thumbPath(?string $attachmentPath): ?string
    {
        if (! $attachmentPath) {
            return null;
        }

        return preg_replace('/\.[A-Za-z0-9]+$/', '', $attachmentPath).'-thumb.webp';
    }
}
