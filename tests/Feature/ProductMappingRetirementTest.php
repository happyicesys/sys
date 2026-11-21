<?php

namespace Tests\Feature;

use App\Models\ProductMapping;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use App\Services\ProductMapping\MappingRetirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Brian, 2026-11-21: a mapping that had machines, was given an upcoming mapping and lost every machine to it is
 * deactivated (never deleted) 3 days after its last machine left.
 */
class ProductMappingRetirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function mapping(string $name, ?int $upcomingId = null, bool $active = true): ProductMapping
    {
        return ProductMapping::withoutGlobalScopes()->create([
            'name' => $name, 'operator_id' => 1, 'is_active' => $active, 'upcoming_product_mapping_id' => $upcomingId,
        ]);
    }

    private function vend(int $code, ?int $mappingId, array $attrs = []): Vend
    {
        $id = DB::table('vends')->insertGetId(array_merge([
            'code' => $code, 'name' => "Machine {$code}", 'operator_id' => 1, 'is_active' => 1, 'is_testing' => 0,
            'product_mapping_id' => $mappingId, 'created_at' => now(), 'updated_at' => now(),
        ], $attrs));

        return Vend::withoutGlobalScopes()->findOrFail($id);
    }

    private function retire(): void
    {
        $this->artisan('product-mappings:retire-emptied --apply')->assertSuccessful();
    }

    private function isActive(ProductMapping $mapping): bool
    {
        return (bool) DB::table('product_mappings')->where('id', $mapping->id)->value('is_active');
    }

    public function test_mapping_is_deactivated_three_days_after_its_last_machine_moved(): void
    {
        $new = $this->mapping('2611');
        $old = $this->mapping('2610', $new->id);
        $a = $this->vend(2001, $old->id);
        $b = $this->vend(2002, $old->id);

        $this->travelTo(now()->setTime(10, 0));
        $a->update(['product_mapping_id' => $new->id]);
        $this->retire();
        $this->assertTrue($this->isActive($old), 'one machine is still on it');

        $b->update(['product_mapping_id' => $new->id]);
        $this->assertNotNull(DB::table('product_mappings')->where('id', $old->id)->value('last_machine_left_at'));

        $this->travel(3)->days();
        $this->travel(-1)->minutes();
        $this->retire();
        $this->assertTrue($this->isActive($old), 'not yet 3 days');

        $this->travel(2)->minutes();
        $this->retire();
        $this->assertFalse($this->isActive($old));
        $this->assertTrue($this->isActive($new));
        $this->assertDatabaseHas('product_mappings', ['id' => $old->id]); // deactivated, never deleted
        $this->assertDatabaseHas('user_logs', [
            'auditable_type' => ProductMapping::class, 'auditable_id' => $old->id, 'source' => MappingRetirement::LOG_SOURCE,
        ]);
    }

    public function test_a_machine_still_bound_in_any_status_keeps_it_active(): void
    {
        $new = $this->mapping('2611');
        $old = $this->mapping('2610', $new->id);
        $this->vend(2003, $old->id, ['is_active' => 0, 'is_disposed' => 1]);
        DB::table('product_mappings')->where('id', $old->id)->update(['last_machine_left_at' => now()->subDays(10)]);

        $this->retire();

        $this->assertTrue($this->isActive($old));
    }

    public function test_a_mapping_without_an_upcoming_mapping_or_never_left_is_untouched(): void
    {
        $plain = $this->mapping('no upcoming');
        DB::table('product_mappings')->where('id', $plain->id)->update(['last_machine_left_at' => now()->subDays(10)]);
        $target = $this->mapping('target');
        $copy = $this->mapping('2610-replicated', $target->id); // a copy no machine ever sat on

        $this->retire();

        $this->assertTrue($this->isActive($plain));
        $this->assertTrue($this->isActive($copy));
    }

    public function test_a_mapping_machines_are_still_waiting_to_move_onto_stays(): void
    {
        $final = $this->mapping('2611');
        $middle = $this->mapping('2610', $final->id);
        $first = $this->mapping('2609', $middle->id);
        $this->vend(2004, $first->id); // its next step is still $middle
        DB::table('product_mappings')->where('id', $middle->id)->update(['last_machine_left_at' => now()->subDays(10)]);

        $this->retire();

        $this->assertTrue($this->isActive($middle));
    }

    public function test_the_audit_log_counts_as_proof_a_machine_left(): void
    {
        $new = $this->mapping('2611');
        $old = $this->mapping('2610', $new->id);
        $vend = $this->vend(2005, $new->id);
        DB::table('user_logs')->insert([
            'user_name' => 'driver', 'event' => 'updated', 'auditable_type' => Vend::class, 'auditable_id' => $vend->id,
            'changes' => json_encode(['product_mapping_id' => [$old->id, $new->id]]), 'source' => 'web',
            'created_at' => now()->subDays(4),
        ]);

        $this->retire();

        $this->assertFalse($this->isActive($old));
    }

    public function test_vm_binding_bulk_move_starts_the_clock(): void
    {
        $new = $this->mapping('2611');
        $old = $this->mapping('2610', $new->id);
        $vend = $this->vend(2006, $old->id);
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read product-mappings', 'web'), Permission::findOrCreate('update product-mappings', 'web'));

        $this->actingAs($user)
            ->post("/product-mappings/{$new->id}/update/vends", ['productMappingVends' => [['id' => $vend->id]]])
            ->assertRedirect();

        $this->assertSame($new->id, (int) DB::table('vends')->where('id', $vend->id)->value('product_mapping_id'));
        $this->assertNotNull(DB::table('product_mappings')->where('id', $old->id)->value('last_machine_left_at'));
        $this->assertNull(DB::table('product_mappings')->where('id', $new->id)->value('last_machine_left_at'));
    }

    public function test_a_run_that_deactivates_clears_the_dashboard_mapping_dropdowns(): void
    {
        $new = $this->mapping('2611');
        $old = $this->mapping('2610', $new->id);
        DB::table('product_mappings')->where('id', $old->id)->update(['last_machine_left_at' => now()->subDays(4)]);
        \Illuminate\Support\Facades\Cache::put('product_mapping_options_1', ['stale'], 3600);
        \Illuminate\Support\Facades\Cache::put('upcoming_product_mapping_options_1', ['stale'], 3600);

        $this->retire();

        $this->assertFalse($this->isActive($old));
        $this->assertNull(\Illuminate\Support\Facades\Cache::get('product_mapping_options_1'));
        $this->assertNull(\Illuminate\Support\Facades\Cache::get('upcoming_product_mapping_options_1'));
    }

    public function test_index_shows_when_an_emptied_mapping_will_deactivate(): void
    {
        $new = $this->mapping('2611');
        $old = $this->mapping('2610', $new->id);
        $left = now()->subDay()->startOfMinute();
        DB::table('product_mappings')->where('id', $old->id)->update(['last_machine_left_at' => $left]);
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read product-mappings', 'web'));

        $this->actingAs($user)
            ->get('/product-mappings?vendStatus=all&numberPerPage=All')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('productMappings.data', fn ($rows) => collect($rows)->firstWhere('id', $old->id)['auto_deactivate_on']
                    === $left->copy()->addDays(3)->toIso8601String()
                    && collect($rows)->firstWhere('id', $new->id)['auto_deactivate_on'] === null));
    }
}
