<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use App\ValueObjects\ReportedApkVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The APK version line on the Operation Dashboard (2026-09-16).
 *
 * It used to read vends.apk_ver_json only — the PWRON frame channel — so a
 * smart freezer, which reports ONLY through the OTA check-in
 * (vends.apk_version_code), showed a blank version however recently it had
 * checked in. Both channels now merge in ReportedApkVersion, for the rendered
 * line and for the "APK Ver" filter alike.
 */
class OpsDashboardApkVersionTest extends TestCase
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
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    /** A bound machine reporting through the given channel(s). */
    private function makeVend(int $code, array $attributes): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}",
            'profile_id' => 1,
            'status_id' => 1,
            'is_active' => 1,
            'operator_id' => $this->hipl->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vends')->insert(array_merge([
            'code' => $code,
            'name' => "Machine {$code}",
            'operator_id' => $this->hipl->id,
            'customer_id' => $customerId,
            'is_active' => 1,
            'is_testing' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function user(): User
    {
        $user = User::factory()->create(['operator_id' => $this->hipl->id]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        OperatorScope::flush();

        return $user;
    }

    /** @return array<int, array<string, mixed>> apkVersion payload keyed by machine code */
    private function apkVersions(User $user, array $query = []): array
    {
        $rows = [];

        $this->actingAs($user)
            ->get('/vends/customers?'.http_build_query(['autoload' => 1] + $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                foreach ($page->toArray()['props']['vends']['data'] as $vend) {
                    $rows[(int) $vend['code']] = $vend['apkVersion'];
                }
            });

        return $rows;
    }

    // ------------------------------------------------------------------ cases

    public function test_it_renders_both_report_channels(): void
    {
        // Vending board: PWRON frame only.
        $this->makeVend(1001, [
            'machine_type' => 'vending_machine',
            'apk_ver_json' => json_encode(['apkver' => 305, 'buildtime' => '2026-09-08 10:00:00', 'deviceType' => 'ZC-328']),
        ]);

        // Smart freezer: OTA check-in only — apk_ver_json stays NULL.
        $this->makeVend(50001, [
            'machine_type' => 'smart_freezer',
            'apk_version_code' => 12,
            'apk_checked_in_at' => '2026-09-16 09:12:03',
        ]);

        $rendered = $this->apkVersions($this->user());

        $this->assertSame(305, $rendered[1001]['code']);
        $this->assertSame(ReportedApkVersion::SOURCE_FRAME, $rendered[1001]['source']);
        $this->assertSame('2026-09-08 10:00:00', $rendered[1001]['build_time']);
        $this->assertNull($rendered[1001]['checked_in_at'], 'a frame report must not pay for a Carbon parse');

        $this->assertSame(12, $rendered[50001]['code'], 'the freezer version came from the OTA column');
        $this->assertSame(ReportedApkVersion::SOURCE_OTA, $rendered[50001]['source']);
        $this->assertNull($rendered[50001]['build_time']);
        $this->assertNotNull($rendered[50001]['checked_in_at']);
    }

    public function test_a_machine_that_never_reported_stays_blank(): void
    {
        $this->makeVend(1002, ['machine_type' => 'vending_machine']);

        $this->assertSame(0, $this->apkVersions($this->user())[1002]['code']);
    }

    public function test_the_apk_ver_filter_matches_either_channel(): void
    {
        $this->makeVend(1001, [
            'machine_type' => 'vending_machine',
            'apk_ver_json' => json_encode(['apkver' => 305]),
        ]);
        $this->makeVend(50001, [
            'machine_type' => 'smart_freezer',
            'apk_version_code' => 12,
            'apk_checked_in_at' => '2026-09-16 09:12:03',
        ]);

        $user = $this->user();

        $this->assertSame([50001], array_keys($this->apkVersions($user, ['apk_ver' => '12'])));
        $this->assertSame([1001], array_keys($this->apkVersions($user, ['apk_ver' => '305'])));

        // Prefix search still works on the frame stream, as it always did.
        $this->assertSame([1001], array_keys($this->apkVersions($user, ['apk_ver' => '30'])));

        // And an absent filter narrows nothing.
        $this->assertEqualsCanonicalizing([1001, 50001], array_keys($this->apkVersions($user)));
    }
}
