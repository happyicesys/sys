<?php

namespace App\Services\ApkMedia;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Fits a UI Setting banner upload (Default / Campaign Media) to the machine.
 *
 * Every bound board downloads every file over a SIM, and the APK decodes a
 * picture at full size, so a raw 12 MP phone photo or a 60 MB clip costs
 * every machine data and memory for pixels its 960 x 1280 frame cannot show.
 * Instead of asking people to export to spec, mark1 does it here:
 *
 * - Pictures are scaled DOWN to fit 960 x 1280 (never up, never cropped —
 *   the frame shows the whole picture), EXIF-rotated, re-encoded (PNG kept
 *   when it fits, else JPEG) and brought under image_max_bytes. GIFs are
 *   stored as uploaded: re-encoding would drop their animation.
 * - Videos are transcoded with ffmpeg to an Android-safe MP4 (H.264 main,
 *   yuv420p, AAC, faststart), fitted to 960 x 1280, at most 30 fps, at a
 *   bitrate sized from the clip's length to land under video_max_bytes. A
 *   clip already in that shape is kept untouched. Without ffmpeg on the
 *   server, videos are stored as uploaded under the old size cap.
 *
 * Limits live in config/apk_media.php.
 */
class BannerMediaNormalizer
{
    public const IMAGE_EXTENSIONS = ['gif', 'jpg', 'jpeg', 'bmp', 'png'];

    public const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'wmv'];

    /** @throws MediaRejected */
    public function normalize(UploadedFile $file, string $ext): NormalizedMedia
    {
        return in_array($ext, self::VIDEO_EXTENSIONS, true)
            ? $this->video($file->getRealPath(), $ext, (int) $file->getSize())
            : $this->image($file->getRealPath(), $ext, (int) $file->getSize());
    }

    public function canProcessVideo(): bool
    {
        return is_executable((string) config('apk_media.ffmpeg'))
            && is_executable((string) config('apk_media.ffprobe'));
    }

    // ---------------------------------------------------------------- images

    private function image(string $path, string $ext, int $size): NormalizedMedia
    {
        $max = (int) config('apk_media.image_max_bytes');

        if ($ext === 'gif') {
            if ($size > $max) {
                throw new MediaRejected('GIF exceeds '.$this->mb($max).' — animated GIFs are not resized; export a smaller one or use a JPG/PNG');
            }

            return new NormalizedMedia($path, $ext, false);
        }

        if ($size > (int) config('apk_media.image_input_max_bytes')) {
            throw new MediaRejected('Picture exceeds '.$this->mb(config('apk_media.image_input_max_bytes')));
        }

        try {
            $image = Image::read($path)->scaleDown(
                width: (int) config('apk_media.frame_width'),
                height: (int) config('apk_media.frame_height'),
            );
        } catch (Throwable $e) {
            throw new MediaRejected('Could not read this picture — is the file damaged?');
        }

        if ($ext === 'png') {
            $png = (string) $image->encode(new PngEncoder);
            if (strlen($png) <= $max) {
                return $this->temp($png, 'png');
            }
        }

        foreach ((array) config('apk_media.jpeg_qualities') as $quality) {
            $jpeg = (string) $image->encode(new JpegEncoder(quality: (int) $quality));
            if (strlen($jpeg) <= $max) {
                return $this->temp($jpeg, 'jpg');
            }
        }

        throw new MediaRejected('Picture is still over '.$this->mb($max).' after resizing to '.$this->frame());
    }

    // ---------------------------------------------------------------- videos

    private function video(string $path, string $ext, int $size): NormalizedMedia
    {
        $max = (int) config('apk_media.video_max_bytes');

        if (! $this->canProcessVideo()) {
            if ($size > $max) {
                throw new MediaRejected('Video exceeds '.$this->mb($max));
            }

            return new NormalizedMedia($path, $ext, false);
        }

        if ($size > (int) config('apk_media.video_input_max_bytes')) {
            throw new MediaRejected('Video exceeds '.$this->mb(config('apk_media.video_input_max_bytes')));
        }

        $probe = $this->probe($path);

        if ($this->alreadyFits($probe, $ext, $size)) {
            return new NormalizedMedia($path, $ext, false);
        }

        $audioBps = $probe['has_audio'] ? (int) config('apk_media.audio_bps') : 0;
        // Leave room for the MP4 container and encoder overshoot.
        $budgetBits = $max * 8 * 0.9;
        $videoBps = (int) min(config('apk_media.video_max_bps'), $budgetBits / $probe['duration'] - $audioBps);
        $minBps = (int) config('apk_media.video_min_bps');
        if ($videoBps < $minBps) {
            $longest = (int) floor($budgetBits / ($minBps + $audioBps));
            throw new MediaRejected(sprintf(
                'Video is %d s long — at most about %d s fits in %s at good quality; trim it and upload again',
                (int) round($probe['duration']), $longest, $this->mb($max),
            ));
        }

        $out = $this->tempPath('mp4');
        $this->transcode($path, $out, $probe, $videoBps, $audioBps);

        // One retry when the encoder overshot: scale the bitrate by the miss.
        $written = (int) filesize($out);
        if ($written > $max) {
            $videoBps = (int) ($videoBps * ($max / $written) * 0.85);
            if ($videoBps >= $minBps) {
                $this->transcode($path, $out, $probe, $videoBps, $audioBps);
                $written = (int) filesize($out);
            }
        }
        if ($written > $max) {
            @unlink($out);
            throw new MediaRejected('Video is still over '.$this->mb($max).' after converting — trim it and upload again');
        }

        return new NormalizedMedia($out, 'mp4', true);
    }

    /**
     * @return array{width: int, height: int, duration: float, fps: float, codec: string, pix_fmt: string, rotation: int, has_audio: bool, audio_codec: ?string}
     */
    private function probe(string $path): array
    {
        $process = new Process([
            config('apk_media.ffprobe'), '-v', 'error', '-print_format', 'json',
            '-show_format', '-show_streams', $path,
        ]);
        $process->setTimeout(20);
        $process->run();

        $json = json_decode($process->getOutput(), true);
        $streams = collect($json['streams'] ?? []);
        $video = $streams->firstWhere('codec_type', 'video');
        $audio = $streams->firstWhere('codec_type', 'audio');
        $duration = (float) ($json['format']['duration'] ?? $video['duration'] ?? 0);

        if (! $process->isSuccessful() || ! $video || empty($video['width']) || $duration <= 0) {
            throw new MediaRejected('Could not read this video — is the file damaged?');
        }

        // ffmpeg 4.x reports rotation as a stream tag, newer builds as display-matrix side data.
        $rotation = (int) ($video['tags']['rotate'] ?? collect($video['side_data_list'] ?? [])->pluck('rotation')->filter()->first() ?? 0);
        $rotation = (($rotation % 360) + 360) % 360;

        [$num, $den] = array_pad(explode('/', (string) ($video['avg_frame_rate'] ?? $video['r_frame_rate'] ?? '0/1')), 2, 1);
        $fps = (float) $den > 0 ? (float) $num / (float) $den : 0.0;

        $width = (int) $video['width'];
        $height = (int) $video['height'];
        if ($rotation === 90 || $rotation === 270) {
            [$width, $height] = [$height, $width];
        }

        return [
            'width' => $width,
            'height' => $height,
            'duration' => $duration,
            'fps' => $fps,
            'codec' => (string) ($video['codec_name'] ?? ''),
            'pix_fmt' => (string) ($video['pix_fmt'] ?? ''),
            'rotation' => $rotation,
            'has_audio' => $audio !== null,
            'audio_codec' => $audio['codec_name'] ?? null,
        ];
    }

    /** Already what we would produce: re-encoding it would only lose quality. */
    private function alreadyFits(array $probe, string $ext, int $size): bool
    {
        return $ext === 'mp4'
            && $size <= (int) config('apk_media.video_max_bytes')
            && $probe['codec'] === 'h264'
            && $probe['pix_fmt'] === 'yuv420p'
            && $probe['rotation'] === 0
            && $probe['width'] <= (int) config('apk_media.frame_width')
            && $probe['height'] <= (int) config('apk_media.frame_height')
            && $probe['fps'] <= (float) config('apk_media.max_fps') + 0.5
            && in_array($probe['audio_codec'], [null, 'aac'], true);
    }

    private function transcode(string $in, string $out, array $probe, int $videoBps, int $audioBps): void
    {
        // Fit inside the frame, never upscale, even dimensions for yuv420p.
        $scale = min(1.0, config('apk_media.frame_width') / $probe['width'], config('apk_media.frame_height') / $probe['height']);
        $width = max(2, (int) floor($probe['width'] * $scale / 2) * 2);
        $height = max(2, (int) floor($probe['height'] * $scale / 2) * 2);

        $filters = ["scale={$width}:{$height}"];
        $maxFps = (int) config('apk_media.max_fps');
        if ($probe['fps'] > $maxFps + 0.5) {
            $filters[] = "fps={$maxFps}";
        }
        $filters[] = 'format=yuv420p';

        $args = [
            config('apk_media.ffmpeg'), '-nostdin', '-y', '-v', 'error',
            '-i', $in,
            '-map', '0:v:0',
            '-vf', implode(',', $filters),
            '-c:v', 'libx264', '-preset', 'veryfast', '-profile:v', 'main', '-level', '4.0',
            '-b:v', (string) $videoBps,
            '-maxrate', (string) (int) ($videoBps * 1.5),
            '-bufsize', (string) ($videoBps * 2),
            '-threads', (string) config('apk_media.ffmpeg_threads'),
        ];
        $args = $audioBps > 0
            ? [...$args, '-map', '0:a:0', '-c:a', 'aac', '-b:a', (string) $audioBps, '-ac', '2']
            : [...$args, '-an'];
        $args = [...$args, '-map_metadata', '-1', '-movflags', '+faststart', $out];

        $process = new Process($args);
        $process->setTimeout((float) config('apk_media.ffmpeg_timeout_seconds'));

        try {
            $process->run();
        } catch (Throwable $e) {
            @unlink($out);
            throw new MediaRejected('Video took too long to convert — trim it and upload again');
        }

        if (! $process->isSuccessful() || ! is_file($out)) {
            @unlink($out);
            report(new \RuntimeException('Banner video conversion failed: '.trim($process->getErrorOutput())));
            throw new MediaRejected('Could not convert this video');
        }
    }

    // ---------------------------------------------------------------- helpers

    private function temp(string $bytes, string $ext): NormalizedMedia
    {
        $path = $this->tempPath($ext);
        file_put_contents($path, $bytes);

        return new NormalizedMedia($path, $ext, true);
    }

    private function tempPath(string $ext): string
    {
        // tempnam reserves a unique name; the file we write carries the extension.
        $base = tempnam(sys_get_temp_dir(), 'apkmedia');
        @unlink($base);

        return $base.'.'.$ext;
    }

    private function mb(int|float $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1024 / 1024, 1), '0'), '.').' MB';
    }

    private function frame(): string
    {
        return config('apk_media.frame_width').' x '.config('apk_media.frame_height');
    }
}
