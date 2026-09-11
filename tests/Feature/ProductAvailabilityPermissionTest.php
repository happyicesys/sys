<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Product Management > "Warehouse Qty (via API) & Planning"
 * (/products/availability) has its own permission, 'product-availability', and
 * until 2026-09-10 that permission gated nothing: ProductController's
 * constructor put ONE blanket 'read products' in front of every method, so the
 * page came free with Product Management > Products and was unreachable
 * without it.
 *
 * The sheet gives prod_owner this page and NOT Products, which is only
 * expressible once the two gates are separate. These tests pin the split:
 * reads on 'read product-availability', the three in-page writes on
 * 'admin-access product-availability' (the same permission
 * Vend/ProductAvailability.vue uses to render them), and Products itself
 * untouched on 'read products'.
 */
class ProductAvailabilityPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function permissionNames(string $roleName): array
    {
        return Role::findByName($roleName, 'web')->permissions->pluck('name')->all();
    }

    private function product(): Product
    {
        $operator = Operator::firstOrCreate(
            ['code' => 'OP1'],
            ['name' => 'Test Operator']
        );

        return Product::create([
            'code' => 'P-'.uniqid(),
            'name' => 'Test Product',
            'operator_id' => $operator->id,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $operator = Operator::firstOrCreate(
            ['code' => 'OP1'],
            ['name' => 'Test Operator']
        );

        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->assignRole($roleName);

        return $user;
    }

    public function test_prod_owner_reads_the_availability_page_but_not_products(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $prodOwner = $this->permissionNames('prod_owner');

        $this->assertContains('read product-availability', $prodOwner);

        // The write half stays off: availability toggle, Remarks, pick limit.
        $this->assertNotContains('admin-access product-availability', $prodOwner);
        $this->assertNotContains('update product-availability', $prodOwner);

        // ... and so do the three things Brian struck off the sheet on
        // 2026-09-10: the Excel export, the Planning column group, the Remarks
        // notes. Stock balance only.
        $this->assertNotContains('export product-availability', $prodOwner);
        $this->assertNotContains('read product-availability-planning', $prodOwner);
        $this->assertNotContains('read product-availability-notes', $prodOwner);

        // And the page next door — Products, and the self-system ledger — is
        // NOT granted by this. Both hang off 'read products'.
        $this->assertNotContains('read products', $prodOwner);
    }

    public function test_prod_owner_can_open_the_availability_page(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        // The page asks CMS for warehouse qty (config('app.cms_url') is set in
        // phpunit.xml); the gate is what is under test, not the feed.
        Http::fake(['*' => Http::response([], 200)]);

        $this->actingAs($this->userWithRole('prod_owner'))
            ->get('/products/availability')
            ->assertOk();
    }

    public function test_prod_owner_is_refused_products_and_the_ledger(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $user = $this->userWithRole('prod_owner');

        $this->actingAs($user)->get('/products')->assertForbidden();
        $this->actingAs($user)->get('/products/movements')->assertForbidden();
    }

    public function test_prod_owner_cannot_write_anything_on_the_availability_page(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $user = $this->userWithRole('prod_owner');
        $product = $this->product();

        $this->actingAs($user)
            ->post('/products/availability/toggle-is-available', ['product_id' => $product->id])
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/products/availability/update-remarks/'.$product->id, ['remarks' => 'hi'])
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/products/availability/update-max-ops-job-pick-limit/'.$product->id, ['max_ops_job_pick_limit' => 1])
            ->assertForbidden();
    }

    public function test_the_write_half_still_works_for_a_role_that_holds_it(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $supervisor = $this->permissionNames('supervisor');

        $this->assertContains('admin-access product-availability', $supervisor);

        $product = $this->product();

        $this->actingAs($this->userWithRole('supervisor'))
            ->post('/products/availability/toggle-is-available', ['product_id' => $product->id])
            ->assertSuccessful();

        $this->assertFalse((bool) $product->fresh()->is_available);
    }

    public function test_prod_owner_is_refused_the_excel_export(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $this->actingAs($this->userWithRole('prod_owner'))
            ->get('/products/availability/export-excel')
            ->assertForbidden();
    }

    /**
     * The point of the two new permissions: the figures must not merely be
     * hidden by a v-if, they must not reach an external supplier at all.
     */
    public function test_the_payload_carries_no_planning_or_notes_for_prod_owner(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        Http::fake(['*' => Http::response([], 200)]);

        $staff = $this->userWithRole('supervisor');
        $product = $this->product();
        $product->forceFill([
            'remarks' => 'chase @Daniel for 200 cartons',
            'remarks_updated_at' => now(),
            'remarks_updated_by' => $staff->id,
            'is_available_updated_at' => now(),
            'is_available_updated_by' => $staff->id,
        ])->save();

        $row = $this->rowFor($this->userWithRole('prod_owner'), $product->id);

        // Notes: the text, its timestamps and every staff name are gone.
        $this->assertNull($row['remarks']);
        $this->assertNull($row['remarks_updated_at']);
        $this->assertSame('', $row['is_available_updated_at']);
        $this->assertArrayNotHasKey('remarksUpdatedBy', $row);
        $this->assertArrayNotHasKey('isAvailableUpdatedBy', $row);

        // Planning: To Pick Qty, Needed by # of VM, Capped Qty per Channel.
        $this->assertNull($row['needed_qty']);
        $this->assertNull($row['needed_vend_count']);
        $this->assertNull($row['max_ops_job_pick_limit']);
        $this->assertArrayNotHasKey('productLimits', $row);

        // The stock balance columns they were given ARE there.
        foreach ([
            'qty_available_pcs_api',
            'not_yet_sync_api_qty',
            'net_available_qty_pcs_api',
            'available_vend_count',
            'avg_seven_days_count',
            'yesterday_sold_count',
        ] as $key) {
            $this->assertArrayHasKey($key, $row, $key);
        }
    }

    public function test_staff_still_get_the_planning_and_notes_payload(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        Http::fake(['*' => Http::response([], 200)]);

        $staff = $this->userWithRole('supervisor');
        $product = $this->product();
        $product->forceFill([
            'remarks' => 'chase for 200 cartons',
            'remarks_updated_by' => $staff->id,
        ])->save();

        $row = $this->rowFor($staff, $product->id);

        $this->assertSame('chase for 200 cartons', $row['remarks']);
        $this->assertArrayHasKey('remarksUpdatedBy', $row);
        $this->assertSame(0, $row['needed_qty']);
        $this->assertArrayHasKey('productLimits', $row);
    }

    /**
     * Technician holds 'read products' and so could already reach
     * /products/availability by URL before the split (the menu item was
     * hidden). The sheet's Technician column says Yes on that row, so the read
     * half keeps them — the split must not quietly close a door.
     */
    public function test_technician_keeps_the_availability_page(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $technician = $this->permissionNames('technician');

        $this->assertContains('read product-availability', $technician);
        $this->assertNotContains('admin-access product-availability', $technician);

        // The two new permissions are additive for everyone who already had the
        // page — only prod_owner is carved out, so nothing may vanish here.
        foreach (['supervisor', 'technician', 'operator_admin', 'operator_supervisor'] as $role) {
            $names = $this->permissionNames($role);
            $this->assertContains('read product-availability-planning', $names, $role);
            $this->assertContains('read product-availability-notes', $names, $role);
            $this->assertContains('export product-availability', $names, $role);
        }
    }

    /** The Inertia row this viewer receives for one product. */
    private function rowFor(User $user, int $productId): array
    {
        $page = $this->actingAs($user)
            ->get('/products/availability')
            ->assertOk()
            ->viewData('page');

        $row = collect($page['props']['products']['data'])->firstWhere('id', $productId);

        $this->assertNotNull($row, 'product missing from the payload');

        return $row;
    }
}
