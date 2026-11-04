<?php

namespace Tests\Feature;

use App\Jobs\CheckProductWelcomeSketch;
use App\Models\Product;
use App\Models\ProductWelcomeSketch;
use App\Models\ProductWelcomeSketchVersion;
use App\Models\User;
use App\Services\Products\WelcomeSketch\ClaudeSketchChecker;
use App\Services\Products\WelcomeSketch\CutoutMaker;
use App\Services\Products\WelcomeSketch\OpenAiSketchGenerator;
use App\Services\Products\WelcomeSketch\ProductWelcomeSketchService;
use App\Services\Products\WelcomeSketch\SketchChecker;
use App\Services\Products\WelcomeSketch\SketchGenerationException;
use App\Services\Products\WelcomeSketch\SketchGenerator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The AI check of a finished welcome sketch (2026-11-04): it runs once per drawn entry, lands on the
 * album entry and never changes what is live. ClaudeSketchChecker runs through the real SDK against a
 * stubbed HTTP client, so the request and answer shapes are the ones the API sees.
 */
class ProductWelcomeSketchCheckTest extends TestCase
{
    use RefreshDatabase;

    private FakeCheckGenerator $generator;

    private FakeSketchChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        Queue::fake();
        $this->generator = new FakeCheckGenerator;
        $this->app->instance(SketchGenerator::class, $this->generator);
        $this->checker = new FakeSketchChecker;
        $this->app->instance(SketchChecker::class, $this->checker);
        $this->app->instance(CutoutMaker::class, new class implements CutoutMaker
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'fake-cutout';
            }

            public function cutout(string $photo): string
            {
                return ProductWelcomeSketchTest::png();
            }
        });
        Http::fake(fn () => Http::response(ProductWelcomeSketchTest::png(800, 600), 200, ['Content-Type' => 'image/png']));
    }

    private function product(string $code = 'CHK-1'): Product
    {
        $product = Product::forceCreate(['code' => $code, 'name' => "Product {$code}", 'operator_id' => 1]);
        $url = 'https://cdn.test/sys/products/p.png';
        $product->thumbnail()->create(['type' => 1, 'full_url' => $url, 'local_url' => $url]);

        return $product;
    }

    private function service(): ProductWelcomeSketchService
    {
        return app(ProductWelcomeSketchService::class);
    }

    private function drawn(Product $product, ?string $instructions = null): ProductWelcomeSketchVersion
    {
        $version = $this->service()->regenerate($product, null, 1, $instructions)[0];
        $this->service()->generate($version->id);

        return $version->fresh();
    }

    public function test_a_drawn_sketch_is_checked_once_and_a_redelivered_job_does_not_pay_again(): void
    {
        $version = $this->drawn($this->product());

        $this->assertSame(ProductWelcomeSketchVersion::CHECK_PENDING, $version->check_status);
        Queue::assertPushed(CheckProductWelcomeSketch::class, fn ($job) => $job->versionId === $version->id && $job->queue === 'low');

        $this->service()->check($version->id);
        $this->service()->check($version->id);

        $this->assertSame(1, $this->checker->calls);
        $version->refresh();
        $this->assertSame(ProductWelcomeSketchVersion::CHECK_PASSED, $version->check_status);
        $this->assertSame([], $version->check_issues);
        $this->assertSame('fake-check-model', $version->check_model);
        $this->assertNotNull($version->checked_at);
    }

    public function test_a_flagged_drawing_keeps_its_place_on_the_freezers(): void
    {
        $product = $this->product();
        $version = $this->drawn($product);
        $live = ProductWelcomeSketch::query()->where('product_id', $product->id)->first();
        $this->assertSame($version->id, (int) $live->version_id);

        $this->checker->issues = [['type' => SketchChecker::INVENTED_TEXT, 'detail' => 'The bar carries a label the photo does not.']];
        $this->service()->check($version->id);

        $this->assertSame(ProductWelcomeSketchVersion::CHECK_FLAGGED, $version->fresh()->check_status);
        $this->assertSame($version->id, (int) $live->fresh()->version_id, 'advice only: what is live does not move');
        $this->assertSame($version->url, $live->fresh()->url);
    }

    public function test_a_flagged_person_draw_still_waits_for_use_and_can_still_be_used(): void
    {
        $product = $this->product();
        $first = $this->drawn($product);
        $second = $this->drawn($product);
        $this->checker->issues = [['type' => SketchChecker::PACKAGING, 'detail' => 'A wrapper is drawn beside the bar.']];
        $this->service()->check($second->id);

        $live = ProductWelcomeSketch::query()->where('product_id', $product->id)->first();
        $this->assertSame($first->id, (int) $live->version_id);

        $this->service()->select($product, $second->id, null);
        $this->assertSame($second->id, (int) $live->fresh()->version_id);
    }

    public function test_a_failed_check_is_recorded_and_leaves_the_drawing_ready(): void
    {
        $version = $this->drawn($this->product());
        $this->checker->fail = 'Check service failed: overloaded';

        $this->service()->check($version->id);

        $version->refresh();
        $this->assertSame(ProductWelcomeSketchVersion::CHECK_FAILED, $version->check_status);
        $this->assertSame('Check service failed: overloaded', $version->check_error);
        $this->assertSame(ProductWelcomeSketch::STATUS_READY, $version->status);
        $this->assertNull($version->last_error);
    }

    public function test_nothing_is_queued_without_a_key(): void
    {
        $this->checker->configured = false;
        $version = $this->drawn($this->product());

        $this->assertSame(ProductWelcomeSketch::STATUS_READY, $version->status);
        $this->assertNull($version->check_status);
        Queue::assertNotPushed(CheckProductWelcomeSketch::class);
        $this->assertSame([], $this->service()->queueChecksForLive());
    }

    public function test_a_queue_that_is_down_does_not_fail_the_finished_drawing(): void
    {
        $product = $this->product();
        $version = $this->service()->regenerate($product, null)[0];
        $bus = \Mockery::mock(Dispatcher::class);
        $bus->shouldReceive('dispatch')->andThrow(new \RuntimeException('redis is down'));
        $this->app->instance(Dispatcher::class, $bus);

        $this->service()->generate($version->id);

        $version->refresh();
        $this->assertSame(ProductWelcomeSketch::STATUS_READY, $version->status);
        $this->assertSame(ProductWelcomeSketch::SOURCE_GENERATED, $version->source);
        $this->assertNull($version->last_error);
        $this->assertSame($version->id, (int) ProductWelcomeSketch::query()->where('product_id', $product->id)->value('version_id'));
    }

    public function test_a_cutout_is_not_checked(): void
    {
        $this->generator->fail = 'Image service refused';
        $version = $this->drawn($this->product());

        $this->assertSame(ProductWelcomeSketch::SOURCE_CUTOUT, $version->source);
        $this->assertNull($version->check_status);
        Queue::assertNotPushed(CheckProductWelcomeSketch::class);
    }

    public function test_the_person_s_instructions_and_the_drawn_from_photo_reach_the_check(): void
    {
        $version = $this->drawn($this->product(), 'Write MANGO on the stick');

        $this->service()->check($version->id);

        $this->assertSame('Write MANGO on the stick', $this->checker->lastInstructions);
        $this->assertSame('Product CHK-1', $this->checker->lastName);
        Http::assertSent(fn ($request) => $request->url() === 'https://cdn.test/sys/products/p.png');
    }

    public function test_the_page_shows_the_result_and_keeps_polling_only_while_a_check_runs(): void
    {
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read products', 'web'));
        $product = $this->product();
        $version = $this->drawn($product);

        $running = $this->actingAs($user)->getJson("/products/{$product->id}/welcome-sketch")->assertOk();
        $this->assertSame(1, $running->json('checks_running'));
        $this->assertSame('pending', $running->json('versions.0.check.status'));
        $this->assertTrue($running->json('versions.0.check.running'));

        $this->checker->issues = [['type' => SketchChecker::WRONG_PRODUCT, 'detail' => 'The bar is green; the photo shows a red one.']];
        $this->service()->check($version->id);

        $done = $this->actingAs($user)->getJson("/products/{$product->id}/welcome-sketch")->assertOk();
        $this->assertSame(0, $done->json('checks_running'));
        $this->assertSame('flagged', $done->json('versions.0.check.status'));
        $this->assertSame(SketchChecker::WRONG_PRODUCT, $done->json('versions.0.check.issues.0.type'));
    }

    public function test_a_check_a_dead_worker_left_stops_the_polling(): void
    {
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read products', 'web'));
        $product = $this->product();
        $version = $this->drawn($product);
        ProductWelcomeSketchVersion::query()->whereKey($version->id)->toBase()->update(['updated_at' => now()->subHour()]);

        $page = $this->actingAs($user)->getJson("/products/{$product->id}/welcome-sketch")->assertOk();

        $this->assertSame(0, $page->json('checks_running'));
        $this->assertSame('stopped', $page->json('versions.0.check.status'));
    }

    public function test_check_live_queues_only_live_drawn_sketches_never_checked(): void
    {
        $this->checker->configured = false;
        $product = $this->product('LIVE-1');
        $live = $this->drawn($product);
        $notLive = $this->drawn($product); // waits in the album
        $this->checker->configured = true;

        Artisan::call('products:welcome-sketches', ['--check-live' => true, '--dry-run' => true]);
        $this->assertNull($live->fresh()->check_status);
        Queue::assertNotPushed(CheckProductWelcomeSketch::class);

        Artisan::call('products:welcome-sketches', ['--check-live' => true]);
        $this->assertSame(ProductWelcomeSketchVersion::CHECK_PENDING, $live->fresh()->check_status);
        $this->assertNull($notLive->fresh()->check_status);
        Queue::assertPushed(CheckProductWelcomeSketch::class, 1);

        $this->service()->check($live->id);
        $this->assertSame([], $this->service()->queueChecksForLive(), 'a checked drawing is not paid for again');
    }

    /** @return array{0: ClaudeSketchChecker, 1: \ArrayObject} */
    private function claude(Response ...$responses): array
    {
        config(['services.anthropic.api_key' => 'test-key']);
        $history = new \ArrayObject;
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return [new ClaudeSketchChecker(new GuzzleClient(['handler' => $stack])), $history];
    }

    private static function apiMessage(string $text, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => [
                ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
                ['type' => 'text', 'text' => $text],
            ],
            'stop_reason' => $stopReason, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 1800, 'output_tokens' => 120],
        ]));
    }

    public function test_claude_is_sent_both_images_the_brief_and_a_json_schema(): void
    {
        [$checker, $history] = $this->claude(self::apiMessage(json_encode(['issues' => [
            ['type' => 'invented_text', 'detail' => 'The drawing says MANGO; the photo has no words.'],
        ]])));

        $issues = $checker->check(ProductWelcomeSketchTest::png(1600, 1200), ProductWelcomeSketchTest::png(), 'Mango Bar', 'Keep the stick');

        $this->assertSame([['type' => 'invented_text', 'detail' => 'The drawing says MANGO; the photo has no words.']], $issues);
        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('https://api.anthropic.com/v1/messages', (string) $request->getUri());
        $this->assertSame('Bearer test-key', $request->getHeaderLine('Authorization'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('low', $body['output_config']['effort']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame(SketchChecker::FAULT_TYPES, $body['output_config']['format']['schema']['properties']['issues']['items']['properties']['type']['enum']);
        $this->assertStringContainsString(OpenAiSketchGenerator::PROMPT, $body['system']);
        $content = $body['messages'][0]['content'];
        $this->assertStringContainsString('Keep the stick', $content[0]['text']);
        $images = array_values(array_filter($content, fn ($block) => $block['type'] === 'image'));
        $this->assertCount(2, $images);
        $this->assertSame('image/jpeg', $images[0]['source']['media_type']);
        $size = getimagesizefromstring(base64_decode($images[0]['source']['data']));
        $this->assertSame(768, max($size[0], $size[1]), 'a large photo is sized down before sending');
    }

    public function test_claude_finding_nothing_is_an_empty_list(): void
    {
        [$checker] = $this->claude(self::apiMessage('{"issues":[]}'));

        $this->assertSame([], $checker->check(ProductWelcomeSketchTest::png(), ProductWelcomeSketchTest::png(), 'Mango Bar'));
    }

    /** @return array<string, array{0: Response}> */
    public static function badAnswers(): array
    {
        return [
            'declined' => [self::apiMessage('', 'refusal')],
            'cut off' => [self::apiMessage('{"issues":[', 'max_tokens')],
            'not json' => [self::apiMessage('Looks fine to me.')],
            'unknown fault type' => [self::apiMessage('{"issues":[{"type":"ugly","detail":"x"}]}')],
            'bad key' => [new Response(401, ['Content-Type' => 'application/json'], '{"type":"error","error":{"type":"authentication_error","message":"invalid x-api-key"}}')],
        ];
    }

    /** @dataProvider badAnswers */
    public function test_claude_answering_badly_never_reads_as_passed(Response $answer): void
    {
        [$checker] = $this->claude($answer);

        $this->expectException(SketchGenerationException::class);
        $checker->check(ProductWelcomeSketchTest::png(), ProductWelcomeSketchTest::png(), 'Mango Bar');
    }
}

class FakeCheckGenerator implements SketchGenerator
{
    public ?string $fail = null;

    private int $drawn = 0;

    public function isConfigured(): bool
    {
        return true;
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function generate(string $photo, string $productName, array $styleReferences, ?string $instructions = null): string
    {
        if ($this->fail !== null) {
            throw new SketchGenerationException($this->fail);
        }
        $this->drawn++;

        return ProductWelcomeSketchTest::png(1024 + $this->drawn * 8, 1024); // each drawing its own file
    }
}

class FakeSketchChecker implements SketchChecker
{
    public bool $configured = true;

    public int $calls = 0;

    /** @var list<array{type: string, detail: string}> */
    public array $issues = [];

    public ?string $fail = null;

    public ?string $lastInstructions = null;

    public ?string $lastName = null;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function model(): string
    {
        return 'fake-check-model';
    }

    public function check(string $photo, string $drawing, string $productName, ?string $instructions = null): array
    {
        $this->calls++;
        $this->lastInstructions = $instructions;
        $this->lastName = $productName;
        if ($this->fail !== null) {
            throw new SketchGenerationException($this->fail);
        }

        return $this->issues;
    }
}
