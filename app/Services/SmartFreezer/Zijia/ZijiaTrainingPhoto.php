<?php

namespace App\Services\SmartFreezer\Zijia;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;
use Throwable;

/**
 * Every photo mark1 sends Zijia for AI training is square (Zijia, 2026-10-09: "every picture
 * uploaded to the API must be 1:1"). The photo is PADDED, never cropped or stretched (Brian,
 * 2026-10-09): the whole pack stays in frame and undistorted, and the shorter side gets an even
 * border — white on a photo, transparent on a cut-out (a PNG/WebP with alpha, like the model
 * photos Zijia approved in vms4), so dropping the alpha also gives white, never black.
 *
 * The long side is capped at MAX_EDGE (never enlarged): Zijia's approved photos are ~2000 px, and a
 * 12 MP phone photo would otherwise send four times the pixels for nothing. EXIF rotation is applied
 * first (image.options.autoOrientation), so a portrait phone shot is squared upright.
 *
 * Squared files live in `sys/zijia-sku/{product}/square/{sha1}.{jpg|png}` — that folder IS the
 * proof a photo is square, so isSquare() costs no download. The original upload is kept beside it
 * (`…/original/`), so a later change of Zijia's rules can be re-applied without a re-shoot.
 */
class ZijiaTrainingPhoto
{
    /** Longest side of a squared photo, in px. */
    public const MAX_EDGE = 2048;

    /** High enough that the AI sees print and texture as shot; ~150–600 KB at 2048 px. */
    private const JPEG_QUALITY = 90;

    /** A photo we fetch (a product thumbnail, a vms4 copy) larger than this is refused, not read. */
    private const MAX_FETCH_BYTES = 20 * 1024 * 1024;

    /** Transparent white: invisible on a cut-out, white wherever a reader drops the alpha. */
    private const CLEAR = 'rgba(255, 255, 255, 0)';

    private const WHITE = 'ffffff';

    /**
     * Squares an uploaded photo and stores it with its original.
     *
     * @return array{url: string, original_url: string, from: string, to: string, bytes: int}
     */
    public function storeUpload(UploadedFile $file, int $productId): array
    {
        $original = (string) file_get_contents($file->getRealPath());
        $square = $this->square($original);
        $originalPath = "sys/zijia-sku/{$productId}/original/".sha1($original).'.'.$this->extension($file);

        return $this->store($square, $productId) + [
            'original_url' => $this->put($originalPath, $original),
        ];
    }

    /**
     * Squares a photo already stored somewhere (the product thumbnail a draft starts from, photos
     * carried over from an earlier or vms4 application). Null when it cannot be read — the caller
     * keeps the link and `missing()` blocks the submit until someone replaces it.
     *
     * @return array{url: string, original_url: string, from: string, to: string, bytes: int}|null
     */
    public function squareUrl(string $url, int $productId): ?array
    {
        try {
            return $this->store($this->square($this->fetch($url)), $productId) + ['original_url' => $url];
        } catch (Throwable $e) {
            Log::warning('Zijia training photo could not be squared', ['url' => $url, 'product' => $productId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** True for a photo this class made, by its folder — no download. */
    public function isSquare(?string $url): bool
    {
        $path = (string) parse_url((string) $url, PHP_URL_PATH);

        return (bool) preg_match('#/zijia-sku/\d+/square/[0-9a-f]{40}\.(jpg|png)$#', $path);
    }

    /**
     * The square image: padded to max(width, height), long side capped at MAX_EDGE, upright.
     *
     * @return array{bytes: string, ext: string, from: string, to: string}
     */
    public function square(string $bytes): array
    {
        $image = Image::read($bytes);
        if ($image->isAnimated()) {
            $image->removeAnimation();
        }
        $from = $image->width().'×'.$image->height();
        $side = min(max($image->width(), $image->height()), self::MAX_EDGE);
        $cutout = $this->hasAlpha($bytes);

        // pad() scales DOWN to fit (never up) and centres the image on a side×side canvas.
        $image->pad($side, $side, $cutout ? self::CLEAR : self::WHITE, 'center');
        $out = (string) $image->encode($cutout ? new PngEncoder : new JpegEncoder(quality: self::JPEG_QUALITY));

        return ['bytes' => $out, 'ext' => $cutout ? 'png' : 'jpg', 'from' => $from, 'to' => "{$side}×{$side}"];
    }

    /** @return array{url: string, from: string, to: string, bytes: int} */
    private function store(array $square, int $productId): array
    {
        $path = "sys/zijia-sku/{$productId}/square/".sha1($square['bytes']).'.'.$square['ext'];

        return ['url' => $this->put($path, $square['bytes']), 'from' => $square['from'], 'to' => $square['to'], 'bytes' => strlen($square['bytes'])];
    }

    private function put(string $path, string $bytes): string
    {
        if (! Storage::put($path, $bytes, 'public')) {
            throw new RuntimeException("Could not store {$path}");
        }

        return Storage::url($path);
    }

    /** Our own files are read from the disk (no HTTP round trip through the CDN); others over https. */
    private function fetch(string $url): string
    {
        $base = (string) preg_replace('#x$#', '', Storage::url('x'));
        if ($base !== '' && str_starts_with($url, $base)) {
            $bytes = Storage::get(rawurldecode(substr($url, strlen($base))));
            if (is_string($bytes) && $bytes !== '') {
                return $bytes;
            }
        }
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('Not an https link');
        }

        $response = Http::timeout(15)->retry(2, 500, throw: false)->get($url);
        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            throw new RuntimeException("Not an image (HTTP {$response->status()})");
        }
        if (strlen($response->body()) > self::MAX_FETCH_BYTES) {
            throw new RuntimeException('Larger than '.(self::MAX_FETCH_BYTES >> 20).' MB');
        }

        return $response->body();
    }

    /**
     * Whether the file can carry transparency, from its header (no pixel scan): a PNG with an alpha
     * colour type or a tRNS chunk, or a WebP whose VP8X header sets the alpha flag.
     */
    private function hasAlpha(string $bytes): bool
    {
        if (str_starts_with($bytes, "\x89PNG")) {
            return in_array(ord($bytes[25] ?? "\0"), [4, 6], true) || str_contains(substr($bytes, 0, 4096), 'tRNS');
        }
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 8) === 'WEBPVP8X') {
            return (ord($bytes[20] ?? "\0") & 0x10) !== 0;
        }

        return false;
    }

    private function extension(UploadedFile $file): string
    {
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));

        return preg_match('/^[a-z0-9]{2,5}$/', $ext) ? $ext : 'bin';
    }
}
