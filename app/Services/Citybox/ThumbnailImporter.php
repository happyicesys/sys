<?php

namespace App\Services\Citybox;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Pulls a CityBox product image onto OUR storage, capped in size (Brian,
 * 2026-08-19: "max size capped at 490kb"), so the products grid never hot-links
 * an oversized CDN file. Same disk + folder family as a hand-uploaded product
 * thumbnail (sys/products/…), so nothing downstream can tell the difference.
 *
 * Shrink strategy: scale down to MAX_EDGE px, then re-encode at falling quality
 * (and, if still too big, falling edge) until ≤ MAX_BYTES. PNG with transparency
 * is kept as PNG; everything else goes JPEG; WebP is the last resort.
 *
 * Returns null on any failure (bad URL, not an image, timeout) — the caller
 * decides what to fall back to. Never throws into a sync.
 */
class ThumbnailImporter
{
    public const MAX_BYTES = 490 * 1024;

    public const MAX_EDGE = 800;

    /**
     * @return array{local_url:string, full_url:string, bytes:int}|null
     */
    public function import(string $url, string $baseName): ?array
    {
        try {
            $response = Http::timeout(15)->retry(2, 500)->get($url);
            if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                Log::notice('Citybox thumbnail: not an image', ['url' => $url, 'status' => $response->status(), 'type' => $response->header('Content-Type')]);

                return null;
            }
            [$binary, $ext] = $this->shrink($response->body());
        } catch (\Throwable $e) {
            Log::notice('Citybox thumbnail import failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        $disk = $this->disk();
        $relative = 'sys/products/citybox/'.$baseName.'.'.$ext;
        Storage::disk($disk)->put($relative, $binary, ['visibility' => 'public']);

        return ['local_url' => $relative, 'full_url' => Storage::disk($disk)->url($relative), 'bytes' => strlen($binary)];
    }

    /** @return array{0:string,1:string} [binary, extension] */
    public function shrink(string $original): array
    {
        $hasAlpha = $this->hasTransparency($original);
        $edge = self::MAX_EDGE;

        while (true) {
            $img = Image::read($original)->scaleDown($edge, $edge);

            if ($hasAlpha) {
                $out = (string) $img->encode(new PngEncoder);
                if (strlen($out) <= self::MAX_BYTES) {
                    return [$out, 'png'];
                }
            }
            foreach ([85, 75, 65, 50] as $q) {
                $out = (string) $img->encode(new JpegEncoder(quality: $q));
                if (strlen($out) <= self::MAX_BYTES) {
                    return [$out, 'jpg'];
                }
            }
            $out = (string) $img->encode(new WebpEncoder(quality: 50));
            if (strlen($out) <= self::MAX_BYTES || $edge <= 200) {
                return [$out, 'webp'];
            }
            $edge = (int) ($edge * 0.7);
        }
    }

    private function hasTransparency(string $binary): bool
    {
        // PNG signature + a tRNS chunk or a colour type with alpha (4 / 6).
        if (! str_starts_with($binary, "\x89PNG")) {
            return false;
        }
        $colourType = ord($binary[25] ?? "\0");

        return in_array($colourType, [4, 6], true) || str_contains(substr($binary, 0, 4096), 'tRNS');
    }

    private function disk(): string
    {
        $default = config('filesystems.default', 'public');

        return $default === 'local' ? 'public' : $default;
    }
}
