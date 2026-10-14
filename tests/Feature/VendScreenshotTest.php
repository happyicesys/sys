<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Models\User;
use App\Models\Vend;
use App\Support\ScreenshotEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Setting/Edit "View Screen" (VendScreenshotController, ScreenshotEligibility): which machines can
 * be asked, the frame goes on the `default` queue with a cooldown, and the machine either delivers
 * a picture or says why not (busy / failed) instead of leaving the operator on a timeout.
 */
class VendScreenshotTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
        $this->user = User::factory()->create(['operator_id' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('update machine-settings', 'web'));
    }

    private function vend(int $code, string $type = Vend::MACHINE_TYPE_VENDING_MACHINE, int $apk = 317, ?string $deviceType = 'ZC-328'): Vend
    {
        $vend = new Vend;
        $vend->forceFill([
            'code' => $code, 'machine_type' => $type, 'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1,
            'apk_ver_json' => $type === Vend::MACHINE_TYPE_VENDING_MACHINE
                ? ['apkver' => $apk, 'buildtime' => '2026-10-14', 'deviceType' => $deviceType] : null,
            'apk_version_code' => $type === Vend::MACHINE_TYPE_SMART_FREEZER ? $apk : null,
            'private_key' => 'TESTKEY000000001',
        ])->save();

        return $vend;
    }

    /** Ask, and return the single-use token the frame carried. */
    private function ask(Vend $vend): string
    {
        $this->actingAs($this->user)->postJson("/vends/{$vend->id}/screenshot")->assertOk()->assertJsonPath('status', 'requested');

        return (string) Cache::get('vend_screenshot_token_'.$vend->id);
    }

    public function test_only_boards_with_a_handler_can_be_asked(): void
    {
        $this->assertNull(ScreenshotEligibility::reason($this->vend(2031, apk: 317)));
        $this->assertStringContainsString('v317 or newer. This machine is on v316', ScreenshotEligibility::reason($this->vend(2032, apk: 316)));
        $this->assertStringContainsString('Small board (ZC-83A)', ScreenshotEligibility::reason($this->vend(2033, apk: 999, deviceType: 'ZC-83A')));
        $this->assertNull(ScreenshotEligibility::reason($this->vend(50001, Vend::MACHINE_TYPE_SMART_FREEZER, 12)));
        $this->assertStringContainsString('freezer app v12', ScreenshotEligibility::reason($this->vend(50002, Vend::MACHINE_TYPE_SMART_FREEZER, 11)));
        $this->assertStringContainsString('CityBox', ScreenshotEligibility::reason($this->vend(6003, Vend::MACHINE_TYPE_SMART_CHILLER)));

        $old = Vend::where('code', 2032)->first();
        $this->actingAs($this->user)->postJson("/vends/{$old->id}/screenshot")
            ->assertStatus(422)->assertJsonPath('status', 'unsupported');
        Queue::assertNothingPushed();
    }

    public function test_the_frame_goes_on_the_default_queue_and_a_second_click_waits(): void
    {
        $vend = $this->vend(2031);

        $token = $this->ask($vend);
        $this->assertSame(40, strlen($token));
        Queue::assertPushedOn('default', PublishMqtt::class);
        $this->actingAs($this->user)->getJson("/vends/{$vend->id}/screenshot")->assertJsonPath('status', 'pending');

        $this->actingAs($this->user)->postJson("/vends/{$vend->id}/screenshot")->assertStatus(429);
        Queue::assertPushed(PublishMqtt::class, 1);
    }

    public function test_a_busy_machine_says_why_and_the_token_is_spent(): void
    {
        $vend = $this->vend(2031);
        $token = $this->ask($vend);

        $this->postJson('/api/v1/vends/2031/screenshot', [
            'token' => $token, 'status' => 'busy', 'reason' => 'Machine in use for 60 s: dispensing',
        ])->assertOk();

        $this->actingAs($this->user)->getJson("/vends/{$vend->id}/screenshot")
            ->assertJsonPath('status', 'busy')
            ->assertJsonPath('message', 'Machine in use for 60 s: dispensing');
        $this->actingAs($this->user)->get("/vends/{$vend->id}/screenshot/image")->assertNotFound();

        // One token, one answer: a replay (or a late picture) is refused.
        $this->post('/api/v1/vends/2031/screenshot', [
            'token' => $token, 'file' => UploadedFile::fake()->image('s.jpg', 54, 96),
        ])->assertForbidden();
    }

    public function test_a_picture_lands_and_is_served_only_to_the_permitted(): void
    {
        $vend = $this->vend(2031);
        $token = $this->ask($vend);

        $this->post('/api/v1/vends/2031/screenshot', [
            'token' => $token, 'file' => UploadedFile::fake()->image('s.jpg', 54, 96),
        ])->assertOk();

        $this->actingAs($this->user)->getJson("/vends/{$vend->id}/screenshot")->assertJsonPath('status', 'ready');
        $this->actingAs($this->user)->get("/vends/{$vend->id}/screenshot/image")
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $viewer = User::factory()->create(['operator_id' => 1]);
        $this->actingAs($viewer)->get("/vends/{$vend->id}/screenshot/image")->assertForbidden();
    }

    public function test_an_upload_needs_a_picture_or_a_known_status(): void
    {
        $vend = $this->vend(2031);
        $token = $this->ask($vend);

        $this->postJson('/api/v1/vends/2031/screenshot', ['token' => $token])->assertStatus(422);
        $this->postJson('/api/v1/vends/2031/screenshot', ['token' => $token, 'status' => 'reboot'])->assertStatus(422);
        $this->postJson('/api/v1/vends/2031/screenshot', ['token' => 'wrong', 'status' => 'failed'])->assertForbidden();
        $this->actingAs($this->user)->getJson("/vends/{$vend->id}/screenshot")->assertJsonPath('status', 'pending');
    }
}
