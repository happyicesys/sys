<?php

namespace App\Services\Products\WelcomeSketch;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Background removal with rembg (MIT, github.com/danielgatis/rembg) run as a CLI on the app server:
 * `rembg i -m <model> in out`, ~2–8 s a photo on CPU (~1.2 GB while it runs). Installed per user with uv (no sudo, its own
 * Python), so the binary lives at /home/forge/.local/bin/rembg on prod and its model under
 * /home/forge/.rembg (REMBG_HOME, passed explicitly so the worker's HOME does not matter).
 * Unavailable (and never called) when the binary is missing.
 */
class RembgCutout implements CutoutMaker
{
    public function isAvailable(): bool
    {
        $bin = $this->binary();

        return $bin !== '' && is_file($bin) && is_executable($bin);
    }

    public function name(): string
    {
        return 'rembg:'.$this->modelName();
    }

    public function cutout(string $photo): string
    {
        $dir = sys_get_temp_dir().'/welcome-sketch-'.bin2hex(random_bytes(6));
        @mkdir($dir, 0700, true);
        $in = "{$dir}/in.img";
        $out = "{$dir}/out.png";
        try {
            file_put_contents($in, $photo);
            $result = Process::timeout(180)->env(array_filter(['REMBG_HOME' => config('smart_freezer.welcome_sketch.rembg_home')]))
                ->run([$this->binary(), 'i', '-m', $this->modelName(), $in, $out]);
            if (! $result->successful() || ! is_file($out) || filesize($out) === 0) {
                throw new SketchGenerationException('Background removal failed: '.mb_substr(trim($result->errorOutput() ?: $result->output()), -300));
            }

            return (string) file_get_contents($out);
        } catch (SketchGenerationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new SketchGenerationException('Background removal failed: '.$e->getMessage(), 0, $e);
        } finally {
            @unlink($in);
            @unlink($out);
            @rmdir($dir);
        }
    }

    private function binary(): string
    {
        return trim((string) config('smart_freezer.welcome_sketch.rembg_bin'));
    }

    private function modelName(): string
    {
        return (string) config('smart_freezer.welcome_sketch.rembg_model', 'isnet-general-use');
    }
}
