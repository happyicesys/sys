<?php

namespace App\Services\Ota;

use App\Models\ApkRelease;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A local, verified copy of a published APK, read in byte ranges for the MQTT
 * transfer (ServeOtaChunkOverMqtt). The release lives in object storage
 * (`apk_releases.file_path`), which cannot seek cheaply, and a transfer is ~140
 * range reads — so it is fetched once per sha, checked against the release's
 * sha256 AND size before first use, and kept under storage/app/ota-cache.
 *
 * Concurrent first requests (several boards starting at once) take one lock: one
 * worker downloads, the others wait for it rather than each pulling 6.7 MB.
 */
class OtaApkCache
{
    public const DIR = 'ota-cache';

    /** Bytes [$offset, $offset + $length) of the release, clipped at its end. */
    public function readRange(ApkRelease $release, int $offset, int $length): string
    {
        $size = (int) $release->size_bytes;
        if ($offset < 0 || $length <= 0 || $offset >= $size) {
            throw new RuntimeException("range {$offset}+{$length} outside 0..{$size}");
        }
        $path = $this->ensure($release);

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new RuntimeException('cannot open cached APK');
        }
        try {
            if (fseek($fh, $offset) !== 0) {
                throw new RuntimeException("cannot seek to {$offset}");
            }
            $want = min($length, $size - $offset);
            $data = '';
            while (strlen($data) < $want && ! feof($fh)) {
                $chunk = fread($fh, $want - strlen($data));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $data .= $chunk;
            }
            if (strlen($data) !== $want) {
                throw new RuntimeException('short read from cached APK');
            }

            return $data;
        } finally {
            fclose($fh);
        }
    }

    /** Absolute path of the verified local copy, downloading it first if needed. */
    public function ensure(ApkRelease $release): string
    {
        $sha = strtolower((string) $release->sha256);
        if (! preg_match('/^[0-9a-f]{64}$/', $sha) || ! $release->file_path) {
            throw new RuntimeException('release has no usable sha256 / file_path');
        }
        $dir = storage_path('app/'.self::DIR);
        $path = $dir.'/'.$sha.'.apk';
        if (is_file($path) && filesize($path) === (int) $release->size_bytes) {
            return $path;
        }

        return Cache::lock('ota-apk-cache:'.$sha, 180)->block(150, function () use ($release, $dir, $path, $sha) {
            if (is_file($path) && filesize($path) === (int) $release->size_bytes) {
                return $path; // another worker finished it while we waited
            }
            if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new RuntimeException("cannot create {$dir}");
            }
            $tmp = $path.'.'.getmypid().'.tmp';
            $in = Storage::readStream($release->file_path);
            if ($in === null || $in === false) {
                throw new RuntimeException('cannot read '.$release->file_path.' from storage');
            }
            $out = fopen($tmp, 'wb');
            try {
                stream_copy_to_stream($in, $out);
            } finally {
                fclose($out);
                if (is_resource($in)) {
                    fclose($in);
                }
            }
            if (filesize($tmp) !== (int) $release->size_bytes || hash_file('sha256', $tmp) !== $sha) {
                @unlink($tmp);
                throw new RuntimeException('downloaded APK does not match the release sha256/size');
            }
            rename($tmp, $path);

            return $path;
        });
    }
}
