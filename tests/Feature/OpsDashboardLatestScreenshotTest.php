<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\VendScreenshot;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Operation Dashboard thumbnail (Brian, 2026-10-16): each row carries its machine's newest View
 * Screen picture (VendController::attachLatestScreenshot) — only for a viewer who may open View
 * Screen, since the picture's route is gated on `update machine-settings`.
 */
class OpsDashboardLatestScreenshotTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    private function makeVend(int $code): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}", 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
            'operator_id' => $this->hipl->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('vends')->insertGetId([
            'code' => $code, 'name' => "Machine {$code}", 'machine_type' => 'vending_machine',
            'operator_id' => $this->hipl->id, 'customer_id' => $customerId,
            'is_active' => 1, 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function shot(int $vendId, ?int $by, string $at): VendScreenshot
    {
        return VendScreenshot::create([
            'vend_id' => $vendId, 'requested_by' => $by, 'path' => "vend-screenshots/{$vendId}/{$at}.jpg",
            'mime' => 'image/jpeg', 'bytes' => 100, 'captured_at' => $at,
        ]);
    }

    private function user(bool $canViewScreen): User
    {
        $user = User::factory()->create(['operator_id' => $this->hipl->id]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        if ($canViewScreen) {
            $user->givePermissionTo(Permission::findOrCreate('update machine-settings', 'web'));
        }
        OperatorScope::flush();

        return $user;
    }

    /** @return array<int, mixed> latest_screenshot keyed by machine code */
    private function thumbnails(User $user): array
    {
        $rows = [];
        $this->actingAs($user)->get('/vends/customers?autoload=1')->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                foreach ($page->toArray()['props']['vends']['data'] as $vend) {
                    $rows[(int) $vend['code']] = $vend['latest_screenshot'];
                }
            });

        return $rows;
    }

    public function test_each_row_carries_its_newest_picture_for_a_view_screen_user(): void
    {
        $a = $this->makeVend(2031);
        $this->makeVend(2032);
        $this->shot($a, 7, '2026-10-16 07:00:05');
        $newest = $this->shot($a, null, '2026-10-16 17:00:09');

        $rows = $this->thumbnails($this->user(true));

        $this->assertSame('2026-10-16 17:00:09', $rows[2031]['captured_at']);
        $this->assertTrue($rows[2031]['auto']);
        $this->assertStringEndsWith("/vends/{$a}/screenshots/{$newest->id}", $rows[2031]['url']);
        $this->assertNull($rows[2032]);
    }

    public function test_a_viewer_who_cannot_open_view_screen_gets_no_thumbnail(): void
    {
        $a = $this->makeVend(2031);
        $this->shot($a, null, '2026-10-16 07:00:05');

        $this->assertNull($this->thumbnails($this->user(false))[2031]);
    }
}
