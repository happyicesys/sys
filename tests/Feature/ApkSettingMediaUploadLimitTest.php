<?php

namespace Tests\Feature;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * UI Setting banner uploads are fitted to the machine (2026-10-09, spec from
 * LOO: 960 x 1280, at most 4 MB): pictures resized + compressed to 1.5 MB,
 * videos transcoded to an MP4 under 4 MB. See BannerMediaNormalizer.
 *
 * The video cases need ffmpeg on the machine running the suite and skip
 * without it; the no-ffmpeg fallback is pinned separately.
 */
class ApkSettingMediaUploadLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $settingId;

    private string $disk;

    /** @var string[] */
    private array $scratch = [];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->disk = config('filesystems.default');
        Storage::fake($this->disk);
        OperatorScope::flush();

        $id = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;
        DB::table('operators')->insert([
            'id' => $id, 'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::factory()->create(['operator_id' => $id]);
        OperatorScope::flush();

        $this->settingId = DB::table('apk_settings')->insertGetId([
            'name' => 'Media', 'operator_id' => $id, 'settings_parameter_json' => '{}',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->scratch as $path) {
            @unlink($path);
        }
        OperatorScope::flush();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    private function upload(UploadedFile $file, string $route = 'upload-media')
    {
        return $this->actingAs($this->user)->post(
            "/apk-settings/{$this->settingId}/{$route}",
            ['files' => $file],
        );
    }

    /** The bytes the machines will download for the newest attachment. */
    private function storedFile(): string
    {
        $row = DB::table('attachments')->latest('id')->first();
        $this->assertNotNull($row, 'nothing was stored');

        return Storage::disk($this->disk)->path($row->local_url);
    }

    private function useFfmpeg(): void
    {
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg'));
        $ffprobe = trim((string) shell_exec('command -v ffprobe'));
        if ($ffmpeg === '' || $ffprobe === '') {
            $this->markTestSkipped('ffmpeg/ffprobe not installed');
        }
        config(['apk_media.ffmpeg' => $ffmpeg, 'apk_media.ffprobe' => $ffprobe]);
    }

    /** A synthetic clip: noise compresses badly, so a high bitrate stays big. */
    private function clip(string $ext, int $w, int $h, int $seconds, string $bitrate, array $codec = ['-c:v', 'libx264', '-pix_fmt', 'yuv420p']): string
    {
        $path = tempnam(sys_get_temp_dir(), 'clip').'.'.$ext;
        $this->scratch[] = $path;
        $process = new Process([
            config('apk_media.ffmpeg'), '-v', 'error', '-y',
            '-f', 'lavfi', '-i', "testsrc2=size={$w}x{$h}:rate=30,noise=alls=60:allf=t",
            '-f', 'lavfi', '-i', 'sine=frequency=440',
            '-t', (string) $seconds, ...$codec, '-b:v', $bitrate, '-c:a', 'aac', $path,
        ]);
        $process->setTimeout(120)->mustRun();

        return $path;
    }

    /** @return array{width: int, height: int, codec: string} */
    private function probeVideo(string $path): array
    {
        $out = (new Process([config('apk_media.ffprobe'), '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height,codec_name', '-of', 'json', $path]))->mustRun()->getOutput();
        $s = json_decode($out, true)['streams'][0];

        return ['width' => (int) $s['width'], 'height' => (int) $s['height'], 'codec' => $s['codec_name']];
    }

    // ---------------------------------------------------------------- pictures

    public function test_a_large_phone_photo_is_fitted_to_960x1280_under_1_5_mb(): void
    {
        $this->upload(UploadedFile::fake()->image('01_promo.jpg', 3000, 4000))->assertOk();

        $path = $this->storedFile();
        [$w, $h] = getimagesize($path);
        $this->assertSame([960, 1280], [$w, $h]);
        $this->assertLessThanOrEqual(config('apk_media.image_max_bytes'), filesize($path));
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertSame('01_promo', DB::table('attachments')->value('name'), 'the name keeps the play order');
    }

    public function test_a_landscape_picture_is_shown_whole_not_cropped(): void
    {
        $this->upload(UploadedFile::fake()->image('wide.png', 2000, 1000), 'upload-campaign-media')->assertOk();

        $path = $this->storedFile();
        $this->assertSame([960, 480], array_slice(getimagesize($path), 0, 2));
        $this->assertStringEndsWith('.png', $path, 'a PNG that fits stays PNG');
    }

    public function test_a_small_picture_is_never_upscaled(): void
    {
        $this->upload(UploadedFile::fake()->image('small.jpg', 300, 400))->assertOk();

        $this->assertSame([300, 400], array_slice(getimagesize($this->storedFile()), 0, 2));
    }

    public function test_a_bmp_is_stored_as_jpg(): void
    {
        $this->upload(UploadedFile::fake()->image('legacy.bmp', 1200, 1600))->assertOk();

        $path = $this->storedFile();
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertSame(IMAGETYPE_JPEG, getimagesize($path)[2]);
    }

    public function test_a_gif_is_kept_as_uploaded_but_capped(): void
    {
        $this->upload(UploadedFile::fake()->image('anim.gif', 200, 200))->assertOk();
        $this->assertStringEndsWith('.gif', $this->storedFile());

        $this->upload(UploadedFile::fake()->create('huge.gif', 1600))
            ->assertStatus(422)
            ->assertJsonFragment(['error_message' => 'GIF exceeds 1.5 MB — animated GIFs are not resized; export a smaller one or use a JPG/PNG']);
    }

    public function test_an_unreadable_picture_is_refused(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('broken.jpg', random_bytes(2048)))
            ->assertStatus(422)
            ->assertJson(['error_message' => 'Could not read this picture — is the file damaged?']);

        $this->assertSame(0, DB::table('attachments')->count());
    }

    // ---------------------------------------------------------------- videos

    public function test_without_ffmpeg_a_video_is_stored_as_uploaded_under_4_mb(): void
    {
        config(['apk_media.ffmpeg' => '/nonexistent/ffmpeg']);

        $this->upload(UploadedFile::fake()->create('ok.mp4', 4 * 1024))->assertOk();
        $this->upload(UploadedFile::fake()->create('big.mov', 4 * 1024 + 1))
            ->assertStatus(422)
            ->assertJson(['error_message' => 'Video exceeds 4 MB']);

        $this->assertSame(1, DB::table('attachments')->count());
    }

    public function test_a_big_landscape_mov_becomes_an_mp4_under_4_mb(): void
    {
        $this->useFfmpeg();
        $source = $this->clip('mov', 1920, 1080, 6, '12M');
        $this->assertGreaterThan(config('apk_media.video_max_bytes'), filesize($source));

        $this->upload(new UploadedFile($source, '02_clip.mov', null, null, true))->assertOk();

        $path = $this->storedFile();
        $this->assertStringEndsWith('.mp4', $path);
        $this->assertLessThanOrEqual(config('apk_media.video_max_bytes'), filesize($path));
        $this->assertSame(['width' => 960, 'height' => 540, 'codec' => 'h264'], $this->probeVideo($path));
    }

    public function test_a_video_already_in_spec_is_kept_byte_for_byte(): void
    {
        $this->useFfmpeg();
        $source = $this->clip('mp4', 960, 1280, 2, '500k');

        $this->upload(new UploadedFile($source, 'ready.mp4', null, null, true))->assertOk();

        $this->assertSame(md5_file($source), md5_file($this->storedFile()));
    }

    public function test_a_clip_too_long_to_fit_4_mb_is_refused(): void
    {
        $this->useFfmpeg();
        // A .mov always needs converting, so its length decides.
        $source = $this->clip('mov', 64, 64, 120, '50k');

        $this->upload(new UploadedFile($source, 'long.mov', null, null, true))
            ->assertStatus(422)
            ->assertJsonPath('error_message', fn ($m) => str_contains($m, 'Video is 120 s long'));

        $this->assertSame(0, DB::table('attachments')->count());
    }
}
