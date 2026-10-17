<?php

namespace Tests\Feature;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Services\VendJobService;
use App\Support\OperatorScope;
use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Audit CORE-01 / UI-01 / SF-03 route gate (2026-10-17): the UI Setting (`/apk-settings`) write
 * routes — save, push to machines, media upload, bind / unbind, delete — were open to every
 * signed-in user. Each verb now has its own permission from RolePermissionSyncSeeder, which this
 * test seeds, so a green run means the gates line up with the seeder's table.
 */
class ApkSettingPermissionGateTest extends TestCase
{
    use RefreshDatabase;

    private int $settingId;

    private int $vendId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(config('filesystems.default'));
        OperatorScope::flush();
        $this->seed(RolePermissionSyncSeeder::class);

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->settingId = DB::table('apk_settings')->insertGetId([
            'name' => 'Gate test', 'operator_id' => 1, 'settings_parameter_json' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->vendId = DB::table('vends')->insertGetId([
            'code' => 2031, 'name' => 'Bench', 'operator_id' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('apk_setting_vend')->insert(['apk_setting_id' => $this->settingId, 'vend_id' => $this->vendId]);
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['operator_id' => 1]);
        $user->assignRole($role);

        return $user;
    }

    public function test_a_driver_cannot_push_save_upload_bind_or_delete_a_ui_setting(): void
    {
        $this->mock(VendJobService::class)->shouldNotReceive('syncSettingsToVend');
        $id = $this->settingId;
        $this->actingAs($this->userWithRole('driver'));

        $this->post("/apk-settings/{$id}/push")->assertForbidden();
        $this->post("/apk-settings/{$id}/update", ['name' => 'Hijacked'])->assertForbidden();
        $this->post("/apk-settings/{$id}/upload-media", ['files' => [UploadedFile::fake()->image('a.jpg')]])->assertForbidden();
        $this->post("/apk-settings/{$id}/upload-campaign-media", ['files' => [UploadedFile::fake()->image('a.jpg')]])->assertForbidden();
        $this->post("/apk-settings/{$id}/campaigns/bind", ['campaign_ids' => [1]])->assertForbidden();
        $this->post('/apk-settings/store', ['name' => 'New'])->assertForbidden();
        $this->delete("/apk-settings/unbind-vend/{$this->vendId}")->assertForbidden();
        $this->delete("/apk-settings/{$id}")->assertForbidden();
        $this->get('/apk-settings')->assertForbidden();

        $this->assertSame('Gate test', DB::table('apk_settings')->where('id', $id)->value('name'));
        $this->assertSame(1, DB::table('apk_setting_vend')->count());
        $this->assertSame(1, DB::table('apk_settings')->count());
    }

    public function test_read_alone_opens_the_page_but_writes_nothing(): void
    {
        Role::findOrCreate('apk_reader', 'web')->syncPermissions(['read apk-settings']);
        $this->mock(VendJobService::class)->shouldNotReceive('syncSettingsToVend');
        $this->actingAs($this->userWithRole('apk_reader'));

        $this->get('/apk-settings')->assertOk();
        $this->post("/apk-settings/{$this->settingId}/push")->assertForbidden();
        $this->post("/apk-settings/{$this->settingId}/update", ['name' => 'Hijacked'])->assertForbidden();
        $this->delete("/apk-settings/{$this->settingId}")->assertForbidden();
    }

    public function test_a_role_the_seeder_grants_ui_settings_can_still_push(): void
    {
        $this->mock(VendJobService::class)->shouldReceive('syncSettingsToVend')->once();

        $this->actingAs($this->userWithRole('technician'))
            ->post("/apk-settings/{$this->settingId}/push")
            ->assertRedirect(route('apk-settings.edit', $this->settingId));
    }
}
