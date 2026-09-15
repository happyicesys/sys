<?php

namespace Tests\Feature;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendResource;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\ProductMapping;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 2026-09-15 audit, findings M2-03..M2-11 + M3-15: every machine / site /
 * ops-job / simcard / role write route was reachable by any signed-in user,
 * and two resources shipped secrets (vends.private_key, operator bank
 * accounts) to every viewer.
 *
 * The roles here carry the SAME grants RolePermissionSyncSeeder gives them for
 * the permissions under test (driver: read/create/update/delete operations,
 * nothing on vends/customers/simcards; operator_admin: update vends), so a
 * green run means the gates line up with the seeder, not with a fixture.
 */
class WriteRoutePermissionGateTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $opA;

    private Operator $opB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();

        // Pinned: OperatorVendFilterScope keys "unrestricted" on id 1 and
        // RefreshDatabase does not reset AUTO_INCREMENT.
        $this->hipl = $this->hiplOperator();
        $this->opA = $this->operator('OPA');
        $this->opB = $this->operator('OPB');

        foreach ([
            'read operations', 'create operations', 'update operations', 'delete operations', 'admin-access operations',
            'read vends', 'update vends', 'admin-access vends',
            'read vend-customers', 'read vend-customers-lite', 'admin-access vend-customers',
            'read machine-view', 'read machine-settings', 'update machine-settings', 'create machine-settings',
            'read customers', 'create customers', 'update customers', 'delete customers',
            'read simcards', 'create simcards', 'update simcards', 'delete simcards',
            'read operators',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('driver', 'web')
            ->syncPermissions(['read operations', 'create operations', 'update operations', 'delete operations', 'admin-access operations']);
        Role::findOrCreate('operator_admin', 'web')
            ->syncPermissions(['read operations', 'create operations', 'update operations', 'delete operations', 'read vends', 'update vends', 'read vend-customers', 'read machine-view', 'read customers', 'update customers']);
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function hiplOperator(): Operator
    {
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => OperatorScope::PARENT_CODE,
            'name' => 'HIPL',
            'is_active' => 1,
            'bank_account_no' => '111-222-333',
            'bank_account_name' => 'HAPPY ICE PTE LTD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
    }

    private function operator(string $code): Operator
    {
        return Operator::withoutGlobalScopes()->firstOrCreate(['code' => $code], [
            'name' => $code,
            'is_active' => true,
            'bank_account_no' => "ACC-{$code}",
            'bank_account_name' => "{$code} LTD",
        ]);
    }

    private function userFor(Operator $operator, string $role): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->assignRole($role);
        OperatorScope::flush();

        return $user;
    }

    private function vendFor(Operator $operator, int $code): Vend
    {
        return Vend::withoutGlobalScopes()->create([
            'code' => $code,
            'name' => "Machine {$code}",
            'operator_id' => $operator->id,
            'private_key' => 'SECRET-'.$code,
        ]);
    }

    private function jobFor(Operator $operator, User $driver, int $code): OpsJob
    {
        return OpsJob::create([
            'code' => $code,
            'date' => now(),
            'operator_id' => $operator->id,
            'delivered_by' => $driver->id,
            'created_by' => $driver->id,
        ]);
    }

    /** @return array<int, array{0:string,1:string}> [method, path] */
    private function gatedWriteRoutes(): array
    {
        return [
            // VendController — M2-03 / M2-10
            ['post', '/vends/1/update'],
            ['post', '/vends/1/unbind-customer'],
            ['post', '/vends/1/unbind'],
            ['post', '/vends/1/unbind-customer-deactivate'],
            ['post', '/vends/1/edit-products'],
            ['post', '/vends/1/replace-product-mapping'],
            ['post', '/vends/1/promote-upcoming-product-mapping'],
            ['post', '/vends/1/restart-apk'],
            ['post', '/vends/1/restart-vmc'],
            ['post', '/vends/1/sync-apk-settings'],
            ['post', '/vends/1/sync-vend-channels'],
            ['post', '/vends/1/trigger-log-upload'],
            ['post', '/vends/create'],
            ['post', '/vends/1/upload-attachments'],
            ['post', '/vends/1/dispense-product'],
            ['post', '/vends/customers/aggregates'],
            // CustomerController — M2-06
            ['post', '/customers/store'],
            ['post', '/customers/1/update'],
            ['post', '/customers/1/bind-vend'],
            ['post', '/customers/1/upload-attachments'],
            ['post', '/customers/1/upload-photos'],
            ['post', '/customers/1/upload-contracts'],
            ['post', '/customers/1/scheduled-contract'],
            ['delete', '/customers/1/scheduled-contract'],
            ['post', '/customers/1/disconnect-cms'],
            ['post', '/customers/1/update-notes'],
            ['post', '/customers/1/update-ops-note'],
            ['post', '/customers/1/update-loc-fee-remarks'],
            ['post', '/customers/1/cms-invoices'],
            ['post', '/customers/cms-invoices/bulk'],
            ['delete', '/customers/1'],
            // SimcardController — M2-11
            ['post', '/simcards/store'],
            ['post', '/simcards/1/update'],
            ['delete', '/simcards/1'],
            // RolePermissionController — M2-05
            ['post', '/roles/create'],
            ['post', '/roles/1/update'],
            ['delete', '/roles/1'],
            ['post', '/permissions/create'],
            ['post', '/permissions/1/update'],
            ['delete', '/permissions/1'],
        ];
    }

    // ------------------------------------------------------------------- tests

    public function test_a_driver_is_refused_on_every_machine_site_simcard_and_role_write_route(): void
    {
        $driver = $this->userFor($this->opA, 'driver');

        foreach ($this->gatedWriteRoutes() as [$method, $path]) {
            $this->actingAs($driver)->{$method}($path)
                ->assertForbidden();
        }
    }

    public function test_a_driver_keeps_the_ops_job_routes_it_works_daily(): void
    {
        $driver = $this->userFor($this->opA, 'driver');
        $job = $this->jobFor($this->opA, $driver, 9001);

        $this->actingAs($driver)
            ->post('/ops-jobs/'.$job->id.'/update', ['remarks' => 'left keys with guard'])
            ->assertRedirect();

        $this->assertSame('left keys with guard', $job->fresh()->remarks);
        $this->assertSame($driver->id, (int) $job->fresh()->updated_by);
    }

    public function test_a_user_without_operations_permissions_is_refused_on_ops_job_writes(): void
    {
        $nobody = User::factory()->create(['operator_id' => $this->opA->id]);
        $driver = $this->userFor($this->opA, 'driver');
        $job = $this->jobFor($this->opA, $driver, 9002);

        $this->actingAs($nobody)->post('/ops-jobs/'.$job->id.'/update', ['remarks' => 'x'])->assertForbidden();
        $this->actingAs($nobody)->delete('/ops-jobs/'.$job->id)->assertForbidden();
        $this->actingAs($nobody)->get('/ops-jobs')->assertForbidden();

        $this->assertNotNull($job->fresh());
    }

    public function test_ops_job_update_no_longer_mass_assigns_operator_and_status(): void
    {
        $driver = $this->userFor($this->opA, 'driver');
        $job = $this->jobFor($this->opA, $driver, 9003);

        $this->actingAs($driver)
            ->post('/ops-jobs/'.$job->id.'/update', [
                'remarks' => 'ok',
                'operator_id' => $this->hipl->id,
                'status' => OpsJob::STATUS_VERIFIED,
                'created_by' => 999,
                'code' => 'HACKED',
            ])
            ->assertRedirect();

        $fresh = $job->fresh();
        $this->assertSame($this->opA->id, (int) $fresh->operator_id);
        $this->assertSame((int) OpsJob::STATUS_PENDING, (int) $fresh->status);
        $this->assertSame($driver->id, (int) $fresh->created_by);
        $this->assertSame(9003, (int) $fresh->code);
    }

    public function test_superadmin_is_not_refused_by_the_new_gates(): void
    {
        $superadmin = $this->userFor($this->hipl, 'superadmin');

        // Routes that stop safely (validation / JSON) before touching MQTT or CMS.
        foreach ([
            ['post', '/vends/customers/aggregates', 200],
            ['post', '/customers/store', 302],
            ['post', '/simcards/store', 302],
            ['post', '/roles/create', 302],
            ['post', '/permissions/create', 302],
        ] as [$method, $path, $status]) {
            $this->actingAs($superadmin)->{$method}($path)->assertStatus($status);
        }
    }

    public function test_an_operator_admin_cannot_move_a_machine_to_another_operator(): void
    {
        $admin = $this->userFor($this->opA, 'operator_admin');
        $vend = $this->vendFor($this->opA, 90001);

        $payload = ['machine_type' => 'smart_freezer', 'vend_model_id' => 1, 'name' => 'Machine 90001'];

        $this->actingAs($admin)
            ->post('/vends/'.$vend->id.'/update', $payload + ['operator_id' => $this->opB->id])
            ->assertSessionHasErrors('operator_id');

        $this->assertSame($this->opA->id, (int) Vend::withoutGlobalScopes()->find($vend->id)->operator_id);

        $this->actingAs($admin)
            ->post('/vends/'.$vend->id.'/update', $payload + ['operator_id' => $this->opA->id])
            ->assertSessionDoesntHaveErrors('operator_id');
    }

    public function test_operator_one_may_still_move_a_machine_between_operators(): void
    {
        $superadmin = $this->userFor($this->hipl, 'superadmin');
        $vend = $this->vendFor($this->opA, 90002);

        $this->actingAs($superadmin)
            ->post('/vends/'.$vend->id.'/update', ['machine_type' => 'smart_freezer', 'vend_model_id' => 1, 'operator_id' => $this->opB->id])
            ->assertSessionDoesntHaveErrors('operator_id');
    }

    public function test_ops_job_index_ignores_a_foreign_operators_filter_for_a_scoped_viewer(): void
    {
        $hiplDriver = $this->userFor($this->hipl, 'driver');
        $aDriver = $this->userFor($this->opA, 'driver');
        $this->jobFor($this->hipl, $hiplDriver, 9010);
        $this->jobFor($this->opA, $aDriver, 9011);

        $codes = [];
        $this->actingAs($aDriver)
            ->get('/ops-jobs?'.http_build_query(['operators' => [$this->hipl->id]]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$codes) {
                $codes = collect($page->toArray()['props']['opsJobs']['data'])->pluck('code')->map(fn ($c) => (int) $c)->all();
            });

        $this->assertSame([9011], $codes);

        // Operator 1 keeps the filter it asked for.
        $codes = [];
        $this->actingAs($hiplDriver)
            ->get('/ops-jobs?'.http_build_query(['operators' => [$this->opA->id]]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$codes) {
                $codes = collect($page->toArray()['props']['opsJobs']['data'])->pluck('code')->map(fn ($c) => (int) $c)->all();
            });

        $this->assertSame([9011], $codes);
    }

    public function test_a_scoped_viewer_cannot_open_or_delete_another_operators_job(): void
    {
        $hiplDriver = $this->userFor($this->hipl, 'driver');
        $aDriver = $this->userFor($this->opA, 'driver');
        $hiplJob = $this->jobFor($this->hipl, $hiplDriver, 9012);

        $this->actingAs($aDriver)->get('/ops-jobs/'.$hiplJob->id.'/edit')->assertNotFound();
        $this->actingAs($aDriver)->post('/ops-jobs/'.$hiplJob->id.'/update', ['remarks' => 'x'])->assertNotFound();
        $this->actingAs($aDriver)->delete('/ops-jobs/'.$hiplJob->id)->assertNotFound();

        $this->assertNotNull($hiplJob->fresh());
        $this->assertNull($hiplJob->fresh()->remarks);
    }

    public function test_vend_resource_hides_the_private_key_from_anyone_who_cannot_update_vends(): void
    {
        $vend = $this->vendFor($this->opA, 90003);

        $this->assertArrayNotHasKey('private_key', $this->resolveAs(new VendResource($vend), $this->userFor($this->opA, 'driver')));
        $this->assertSame('SECRET-90003', $this->resolveAs(new VendResource($vend), $this->userFor($this->opA, 'operator_admin'))['private_key']);
        $this->assertSame('SECRET-90003', $this->resolveAs(new VendResource($vend), $this->userFor($this->hipl, 'superadmin'))['private_key']);
    }

    public function test_operator_resource_hides_bank_details_from_anyone_who_cannot_read_operators(): void
    {
        $payload = $this->resolveAs(new OperatorResource($this->opB), $this->userFor($this->opA, 'driver'));
        $this->assertArrayNotHasKey('bank_account_no', $payload);
        $this->assertArrayNotHasKey('bank_account_name', $payload);

        $payload = $this->resolveAs(new OperatorResource($this->opB), $this->userFor($this->opA, 'operator_admin'));
        $this->assertArrayNotHasKey('bank_account_no', $payload);

        $payload = $this->resolveAs(new OperatorResource($this->opB), $this->userFor($this->hipl, 'superadmin'));
        $this->assertSame('ACC-OPB', $payload['bank_account_no']);
    }

    public function test_search_vend_code_never_returns_the_private_key(): void
    {
        $this->vendFor($this->opA, 90004);

        $rows = $this->getJson('/api/vends/search/9000')->assertOk()->json();

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('private_key', $row);
            $this->assertArrayHasKey('code', $row);
        }
        $this->assertStringNotContainsString('SECRET-90004', json_encode($rows));
    }

    public function test_product_mapping_options_cache_is_per_operator_and_busted_on_mapping_save(): void
    {
        Cache::put('product_mapping_options_'.$this->opA->id, ['stale-a'], 3600);
        Cache::put('product_mapping_options_'.$this->hipl->id, ['stale-hipl'], 3600);

        ProductMapping::withoutGlobalScopes()->create(['name' => 'New mapping', 'operator_id' => $this->opA->id]);

        $this->assertNull(Cache::get('product_mapping_options_'.$this->opA->id));
        $this->assertNull(Cache::get('product_mapping_options_'.$this->hipl->id));
    }

    // ------------------------------------------------------------------ helpers

    private function resolveAs($resource, User $user): array
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        return $resource->resolve($request);
    }
}
