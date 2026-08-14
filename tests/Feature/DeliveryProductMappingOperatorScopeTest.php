<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatformCampaign;
use App\Models\DeliveryProductMapping;
use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Grab Product Mapping (/delivery-product-mappings) operator isolation.
 *
 * The page previously applied NO server-side filter - a leasing customer with a
 * single machine was shown all 37 mappings, and could reach any of them by id
 * through edit/update/delete/bindVend, which all use a bare findOrFail().
 *
 * Visibility rule: a mapping the viewer OWNS, or one bound to a machine of
 * theirs by an active (end_date IS NULL) row. Both arms are exercised here -
 * cross-operator binds are real in production (2026-08-14: KMY machines on a
 * KEA-owned mapping, 11 HIPL machines on a DCVIC-owned one).
 */
class DeliveryProductMappingOperatorScopeTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $opA;

    private Operator $opB;

    protected function setUp(): void
    {
        parent::setUp();

        OperatorScope::flush();

        $this->hipl = $this->hiplOperator();
        $this->opA = $this->operator('OPA');
        $this->opB = $this->operator('OPB');
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function hiplOperator(): Operator
    {
        $id = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;

        DB::table('operators')->insert([
            'id' => $id,
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Operator::withoutGlobalScopes()->findOrFail($id);
    }

    private function operator(string $code): Operator
    {
        return Operator::withoutGlobalScopes()->firstOrCreate(['code' => $code], [
            'name' => $code,
            'is_active' => true,
        ]);
    }

    private function userFor(Operator $operator): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);

        OperatorScope::flush();

        return $user;
    }

    private function makeVend(Operator $operator, int $code): int
    {
        return DB::table('vends')->insertGetId([
            'code' => $code,
            'name' => "Machine {$code}",
            'operator_id' => $operator->id,
            'is_testing' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return int mapping id */
    private function makeMapping(Operator $owner, string $name): int
    {
        return DB::table('delivery_product_mappings')->insertGetId([
            'name' => $name,
            'operator_id' => $owner->id,
            'delivery_platform_operator_id' => 1,
            'product_mapping_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bind(int $mappingId, int $vendId, bool $active = true): void
    {
        DB::table('delivery_product_mapping_vend')->insert([
            'delivery_product_mapping_id' => $mappingId,
            'vend_id' => $vendId,
            'end_date' => $active ? null : now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return string[] mapping names visible to this user */
    private function visibleTo(?User $user): array
    {
        if ($user) {
            $this->actingAs($user);
        }

        return DeliveryProductMapping::query()->orderBy('name')->pluck('name')->all();
    }

    // -------------------------------------------------------------------- tests

    public function test_operator_does_not_see_other_operators_mappings(): void
    {
        $this->makeMapping($this->opA, 'A-OWNED');
        $this->makeMapping($this->opB, 'B-OWNED');

        $this->assertSame(['A-OWNED'], $this->visibleTo($this->userFor($this->opA)));
    }

    /**
     * The requested behaviour: a mapping another operator owns, but which is
     * bound to one of MY machines, must be visible to me.
     */
    public function test_operator_sees_another_operators_mapping_bound_to_its_own_machine(): void
    {
        $mappingId = $this->makeMapping($this->opB, 'B-OWNED-BOUND-TO-A');
        $this->bind($mappingId, $this->makeVend($this->opA, 2001));

        $this->assertSame(['B-OWNED-BOUND-TO-A'], $this->visibleTo($this->userFor($this->opA)));
    }

    /** An ended binding is not a binding - it must not keep the mapping visible. */
    public function test_an_ended_binding_does_not_grant_visibility(): void
    {
        $mappingId = $this->makeMapping($this->opB, 'B-OWNED-UNBOUND-FROM-A');
        $this->bind($mappingId, $this->makeVend($this->opA, 2001), active: false);

        $this->assertSame([], $this->visibleTo($this->userFor($this->opA)));
    }

    /**
     * The owner arm on its own. Without it an operator loses their own mapping
     * the moment nothing is bound to it - including one they just created,
     * which would make the create-then-bind flow impossible.
     */
    public function test_operator_still_sees_its_own_mapping_with_no_bindings(): void
    {
        $this->makeMapping($this->opA, 'A-OWNED-NEVER-BOUND');

        $this->assertSame(['A-OWNED-NEVER-BOUND'], $this->visibleTo($this->userFor($this->opA)));
    }

    public function test_happyice_staff_see_every_mapping(): void
    {
        $this->makeMapping($this->opA, 'A-OWNED');
        $this->makeMapping($this->opB, 'B-OWNED');
        $this->makeMapping($this->hipl, 'HIPL-OWNED');

        $this->assertSame(
            ['A-OWNED', 'B-OWNED', 'HIPL-OWNED'],
            $this->visibleTo($this->userFor($this->hipl))
        );
    }

    /**
     * Grab's webhooks (routes/api.php) are not authenticated - the
     * 'delivery.authprobe' middleware only logs - so order ingestion resolves
     * mappings with no user in context and must stay unrestricted.
     */
    public function test_unauthenticated_callers_are_unrestricted(): void
    {
        $this->makeMapping($this->opA, 'A-OWNED');
        $this->makeMapping($this->opB, 'B-OWNED');

        $this->assertSame(['A-OWNED', 'B-OWNED'], $this->visibleTo(null));
    }

    /**
     * The scope must cover row-level access too, not just the listing: every
     * write endpoint on this controller resolves the model with findOrFail().
     */
    public function test_another_operators_mapping_is_not_reachable_by_id(): void
    {
        $mappingId = $this->makeMapping($this->opB, 'B-OWNED');

        $this->actingAs($this->userFor($this->opA));

        $this->assertNull(DeliveryProductMapping::find($mappingId));
    }

    /**
     * delivery_platform_campaigns carries no operator scope, so a campaign whose
     * mapping is out of scope stays reachable while its ->deliveryProductMapping
     * resolves to null. Callers must not chain off it - see the guard in
     * DeliveryPlatformCampaignController::batchCreateItemVend(), where calling a
     * method on that null is a fatal rather than a warning.
     */
    public function test_out_of_scope_campaign_resolves_a_null_product_mapping(): void
    {
        $mappingId = $this->makeMapping($this->opB, 'B-OWNED');

        $campaignId = DB::table('delivery_platform_campaigns')->insertGetId([
            'name' => 'B-CAMPAIGN',
            'delivery_product_mapping_id' => $mappingId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->userFor($this->opA));

        $campaign = DeliveryPlatformCampaign::findOrFail($campaignId);

        $this->assertNull($campaign->deliveryProductMapping);
    }
}
