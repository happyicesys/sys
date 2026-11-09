<?php

namespace Tests\Feature;

use App\Models\SmartFreezerRecognition;
use App\Models\SmartFreezerVideo;
use App\Models\SmartFreezerVideoCopy;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Our own copy of each Zijia door-session video (Brian, 2026-11-09): copied private into our disk when
 * the push arrives, and played on AI Recognition when their link no longer answers.
 */
class ZijiaVideoCopyTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://jishi1.oss-cn-hangzhou.aliyuncs.com/boxapp/861232069528880/order/SF-50001-1794196595-2/SF-50001-1794196595-2-d1c3-25f-1280x720.mp4';

    private const VIDEO_BYTES = 'mp4-bytes-of-a-door-session';

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        config([
            'smart_freezer.zijia.video_webhook_token' => 'push-token',
            'smart_freezer.zijia.algorithm.auto_submit' => false,
            'smart_freezer.zijia.video_copy.enabled' => true,
            'smart_freezer.zijia.video_copy.hosts' => ['.aliyuncs.com'],
        ]);
        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
    }

    /** What their storage answers now; one fake for the whole test, since a second Http::fake does not replace the first. */
    private int $theirStatus = 200;

    private string $theirType = 'video/mp4';

    private function fakeTheirs(): void
    {
        Http::fake(['jishi1.oss-cn-hangzhou.aliyuncs.com/*' => fn () => Http::response(
            $this->theirStatus === 200 ? self::VIDEO_BYTES : '', $this->theirStatus, ['Content-Type' => $this->theirType],
        )]);
    }

    private function push(string $url = self::URL): void
    {
        $this->postJson('/api/smart-freezer/zijia/videos?token=push-token', [
            'imei' => '861232069528880',
            'orderNo' => 'SF-50001-1794196595-2',
            'videos' => [['camera' => 3, 'url' => $url]],
        ])->assertOk();
    }

    private function viewer(int $operatorId = 1): User
    {
        Permission::findOrCreate('read ai-recognition', 'web');
        $user = User::factory()->create(['operator_id' => $operatorId]);
        $user->givePermissionTo('read ai-recognition');

        return $user;
    }

    public function test_a_push_is_copied_private_into_our_disk(): void
    {
        $this->fakeTheirs();
        $this->push();

        $copy = SmartFreezerVideoCopy::sole();
        $this->assertSame(SmartFreezerVideoCopy::STATUS_COPIED, $copy->status);
        $this->assertSame(strlen(self::VIDEO_BYTES), $copy->bytes);
        $this->assertSame(hash('sha256', self::VIDEO_BYTES), $copy->sha256);
        $this->assertStringStartsWith("freezer-videos/{$this->vend->id}/zijia/", $copy->path);
        Storage::assertExists($copy->path);
        $this->assertSame(self::VIDEO_BYTES, Storage::get($copy->path));
        $this->assertSame('private', Storage::getVisibility($copy->path));
    }

    public function test_a_video_mark1_found_early_is_copied_too(): void
    {
        $this->fakeTheirs();
        SmartFreezerVideo::create([
            'supplier' => 'zijia', 'vend_id' => $this->vend->id, 'order_no' => 'SF-50001-1794196595-2',
            'video_urls' => [self::URL], 'payload' => ['source' => SmartFreezerVideo::SOURCE_EARLY_FETCH],
        ]);

        $this->assertSame(SmartFreezerVideoCopy::STATUS_COPIED, SmartFreezerVideoCopy::sole()->status);
    }

    public function test_the_same_file_pushed_twice_is_copied_once(): void
    {
        $this->fakeTheirs();
        $this->push();
        $this->push();

        $this->assertSame(1, SmartFreezerVideoCopy::count());
        Http::assertSentCount(1);
    }

    public function test_a_link_outside_their_storage_is_never_fetched(): void
    {
        Http::fake();
        $this->push('https://internal.example.test/secret.mp4');
        $this->push('http://jishi1.oss-cn-hangzhou.aliyuncs.com/plain-http.mp4');

        Http::assertNothingSent();
        $this->assertSame(2, SmartFreezerVideoCopy::where('status', SmartFreezerVideoCopy::STATUS_FAILED)->count());
        $this->assertSame('host not allowed for copying', SmartFreezerVideoCopy::first()->error);
    }

    public function test_a_file_that_is_not_a_video_or_too_large_is_refused(): void
    {
        $this->theirType = 'text/html';
        $this->fakeTheirs();
        $this->push();
        $this->assertSame(SmartFreezerVideoCopy::STATUS_FAILED, SmartFreezerVideoCopy::sole()->status);
        $this->assertStringContainsString('not a video', SmartFreezerVideoCopy::sole()->error);

        config(['smart_freezer.zijia.video_copy.max_bytes' => 5]);
        $this->theirType = 'video/mp4';
        $this->artisan('smart-freezer:copy-zijia-videos', ['--apply' => true])->assertSuccessful();
        $this->assertStringContainsString('too large', SmartFreezerVideoCopy::sole()->error);
        $this->assertSame([], Storage::allFiles());
    }

    public function test_the_page_plays_their_link_while_it_answers_and_ours_after(): void
    {
        $this->fakeTheirs();
        $this->push();
        $video = SmartFreezerVideo::sole();
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get("/freezer-zijia-videos/{$video->id}/0")->assertRedirect(self::URL);

        // Their file is gone: our copy plays instead.
        Cache::flush();
        $this->theirStatus = 404;
        $response = $this->actingAs($viewer)->get("/freezer-zijia-videos/{$video->id}/0")->assertRedirect();
        $this->assertStringContainsString('freezer-videos/', $response->headers->get('Location'));
        $this->assertNotSame(self::URL, $response->headers->get('Location'));

        // Gone everywhere: a plain 404.
        Cache::flush();
        Storage::delete(SmartFreezerVideoCopy::sole()->path);
        $this->actingAs($viewer)->get("/freezer-zijia-videos/{$video->id}/0")->assertNotFound();
    }

    public function test_another_operator_cannot_play_it(): void
    {
        $this->fakeTheirs();
        $this->push();
        $video = SmartFreezerVideo::sole();

        $this->actingAs($this->viewer(operatorId: 99))->get("/freezer-zijia-videos/{$video->id}/0")->assertNotFound();
    }

    public function test_the_backfill_copies_what_we_do_not_hold(): void
    {
        config(['smart_freezer.zijia.video_copy.enabled' => false]);
        $this->fakeTheirs();
        $this->push();
        $this->assertSame(0, SmartFreezerVideoCopy::count());

        $this->artisan('smart-freezer:copy-zijia-videos')->expectsOutputToContain('1 file(s) to copy')->assertSuccessful();
        $this->assertSame(0, SmartFreezerVideoCopy::count());

        $this->artisan('smart-freezer:copy-zijia-videos', ['--apply' => true])->expectsOutputToContain('copied 1')->assertSuccessful();
        $this->assertSame(SmartFreezerVideoCopy::STATUS_COPIED, SmartFreezerVideoCopy::sole()->status);
        Http::assertSent(fn (Request $request) => $request->url() === self::URL);
    }

    private function useRealVideo(): void
    {
        $bin = trim((string) shell_exec('command -v ffprobe'));
        if ($bin === '') {
            $this->markTestSkipped('ffprobe is not installed here');
        }
        config(['smart_freezer.clips.ffprobe_bin' => $bin]);
        Http::fake(['jishi1.oss-cn-hangzhou.aliyuncs.com/*' => fn () => Http::response(
            file_get_contents(base_path('tests/Fixtures/door-2_5s.mp4')), 200, ['Content-Type' => 'video/mp4'],
        )]);
    }

    public function test_the_copy_records_how_long_the_video_runs_and_the_row_shows_it(): void
    {
        $this->useRealVideo();
        $this->push();

        $ms = SmartFreezerVideoCopy::sole()->duration_ms;
        $this->assertNotNull($ms);
        $this->assertEqualsWithDelta(2500, $ms, 200);

        $row = $this->actingAs($this->viewer())->get('/ai-recognition?date_from=&date_to=')->assertOk()
            ->viewData('page')['props']['recognitions']['data'][0];
        $this->assertSame(3, $row['video_links'][self::URL]['seconds']);
        $this->assertArrayNotHasKey('pushes', $row);
    }

    public function test_a_file_ffprobe_cannot_read_is_copied_with_no_length(): void
    {
        $this->fakeTheirs();
        $this->push();

        $this->assertSame(SmartFreezerVideoCopy::STATUS_COPIED, SmartFreezerVideoCopy::sole()->status);
        $this->assertNull(SmartFreezerVideoCopy::sole()->duration_ms);
    }

    public function test_the_backfill_measures_copies_made_before_lengths_were_kept(): void
    {
        $this->useRealVideo();
        $this->push();
        SmartFreezerVideoCopy::query()->update(['duration_ms' => null]);

        $this->artisan('smart-freezer:copy-zijia-videos')->expectsOutputToContain('1 copied file(s) with no length yet')->assertSuccessful();
        $this->assertNull(SmartFreezerVideoCopy::sole()->duration_ms);

        $this->artisan('smart-freezer:copy-zijia-videos', ['--apply' => true])->expectsOutputToContain('measured 1')->assertSuccessful();
        $this->assertEqualsWithDelta(2500, SmartFreezerVideoCopy::sole()->duration_ms, 200);
    }

    public function test_the_ai_recognition_row_links_through_mark1_with_no_length_until_measured(): void
    {
        $this->fakeTheirs();
        $this->push();
        $recognition = SmartFreezerRecognition::sole();
        $video = SmartFreezerVideo::sole();

        $row = $this->actingAs($this->viewer())->get('/ai-recognition?date_from=&date_to=')->assertOk()
            ->viewData('page')['props']['recognitions']['data'][0];
        $this->assertSame($recognition->id, $row['id']);
        $this->assertSame([self::URL], $row['videos']);
        $this->assertSame(route('freezer-zijia-videos.show', ['video' => $video->id, 'index' => 0]), $row['video_links'][self::URL]['href']);
        $this->assertNull($row['video_links'][self::URL]['seconds']);
    }
}
