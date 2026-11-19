<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingVend;
use App\Models\Operator;
use App\Models\User;
use App\Models\Vend;
use App\Services\GrabStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * A Grab store's operator follows the machine it sells from (Brian, 2026-11-19): the two Bulla stores moved onto HIPL
 * machines 2688 and 2794 stayed LEA after Remove + Put on machine, on LEA's template.
 */
class GrabStoreFollowsMachineOperatorTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const STORE = '4-C6VGPA2JTY3HJE';

    private User $user;

    private Operator $lea;

    private DeliveryProductMapping $leaTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
        Queue::fake();
        Http::fake(fn ($request) => str_ends_with($request->url(), '/merchant/pause') ? Http::response(null, 204) : Http::response([], 200));

        $hipl = Operator::withoutGlobalScopes()->findOrFail($this->grabMapping->operator_id);
        $this->lea = Operator::forceCreate(['name' => 'Bulla', 'code' => 'LEA', 'country_id' => $hipl->country_id, 'created_by' => 1, 'gst_vat_rate' => 9]);
        $leaAccount = DeliveryPlatformOperator::forceCreate([
            'delivery_platform_id' => $this->grabOperator->delivery_platform_id, 'operator_id' => $this->lea->id, 'type' => 'production',
        ]);
        $this->leaTemplate = DeliveryProductMapping::forceCreate([
            'delivery_platform_operator_id' => $leaAccount->id, 'operator_id' => $this->lea->id, 'product_mapping_id' => 1,
            'name' => 'LEA_14_V01_2408_Grab', 'category_json' => ['id' => 'SGITEDP20200707065055013739', 'name' => 'Ice Cream & Dessert'],
        ]);

        foreach (['read delivery-platform-vends', 'update delivery-platform-vends'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->user = User::factory()->create(['operator_id' => $hipl->id]);
        $this->user->givePermissionTo(['read delivery-platform-vends', 'update delivery-platform-vends']);
    }

    private function service(): GrabStoreService
    {
        return app(GrabStoreService::class);
    }

    private function machine(int $code, int $operatorId): Vend
    {
        return Vend::forceCreate(['code' => $code, 'operator_id' => $operatorId]);
    }

    /** A Bulla store selling from a Bulla machine, the way 4-C6VGPA2JTY3HJE was on 2852. */
    private function bullaStoreOn(int $code): DeliveryProductMappingVend
    {
        $ref = DeliveryPlatformRefNumber::forceCreate([
            'ref_number' => self::STORE, 'operator_id' => $this->lea->id, 'delivery_platform_id' => $this->grabOperator->delivery_platform_id, 'status' => 1,
        ]);

        return $this->service()->bind($ref, $this->machine($code, $this->lea->id), $this->leaTemplate, $this->user->id);
    }

    private function operatorEvents(DeliveryPlatformRefNumber $ref)
    {
        return DeliveryPlatformStoreEvent::query()->where('delivery_platform_ref_number_id', $ref->id)
            ->where('event', DeliveryPlatformStoreEvent::EVENT_EDITED)->get();
    }

    public function test_putting_a_store_on_another_operators_machine_gives_it_that_operator_and_its_template(): void
    {
        $old = $this->bullaStoreOn(2852);
        $ref = $old->deliveryPlatformRefNumber;
        $this->service()->unbind($old, $this->user->id);

        // The old LEA template, as a caller may still pass it: a machine-menu store takes the new operator's usual one.
        $binding = $this->service()->bind($ref->fresh(), $this->machine(2688, $this->grabMapping->operator_id), $this->leaTemplate, $this->user->id);

        $this->assertSame((int) $this->grabMapping->operator_id, (int) $ref->fresh()->operator_id);
        $this->assertSame($this->grabMapping->id, (int) $binding->delivery_product_mapping_id);
        $event = $this->operatorEvents($ref)->sole();
        $this->assertEquals(['from' => $this->lea->id, 'to' => (int) $this->grabMapping->operator_id], $event->data['operator_id']);
        $this->assertSame('Operator follows machine 2688', $event->reason);
    }

    public function test_moving_a_store_to_another_operators_machine_does_the_same(): void
    {
        $old = $this->bullaStoreOn(2853);

        $moved = $this->service()->move($old, $this->machine(2794, $this->grabMapping->operator_id), null, $this->user->id);

        $ref = $moved->deliveryPlatformRefNumber->fresh();
        $this->assertSame((int) $this->grabMapping->operator_id, (int) $ref->operator_id);
        $this->assertSame($this->grabMapping->id, (int) $moved->delivery_product_mapping_id);
        $this->assertCount(1, $this->operatorEvents($ref));
    }

    public function test_the_put_on_machine_window_switches_the_operator_before_prices_are_set_up(): void
    {
        $old = $this->bullaStoreOn(2852);
        $ref = $old->deliveryPlatformRefNumber;
        $this->service()->unbind($old, $this->user->id);
        $this->machine(2688, $this->grabMapping->operator_id);

        $this->actingAs($this->user)->post("/grab-stores/{$ref->id}/bind", ['vend_code' => '2688'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame((int) $this->grabMapping->operator_id, (int) $ref->fresh()->operator_id);
        $this->assertSame($this->grabMapping->id, (int) $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->sole()->delivery_product_mapping_id);
    }

    public function test_the_move_window_switches_the_operator_and_the_template_too(): void
    {
        $old = $this->bullaStoreOn(2853);
        $this->machine(2794, $this->grabMapping->operator_id);

        $r = $this->actingAs($this->user)->post("/grab-stores/bindings/{$old->id}/move", ['vend_code' => '2794', 'reason' => 'moved']);
        $r->assertRedirect()->assertSessionHasNoErrors();

        $ref = $old->deliveryPlatformRefNumber->fresh();
        $this->assertSame((int) $this->grabMapping->operator_id, (int) $ref->operator_id);
        $this->assertSame($this->grabMapping->id, (int) $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->sole()->delivery_product_mapping_id);
    }

    public function test_a_machine_of_the_same_operator_changes_nothing(): void
    {
        $old = $this->bullaStoreOn(2852);

        $this->service()->move($old, $this->machine(2853, $this->lea->id), null, $this->user->id);

        $this->assertSame($this->lea->id, (int) $old->deliveryPlatformRefNumber->fresh()->operator_id);
        $this->assertCount(0, $this->operatorEvents($old->deliveryPlatformRefNumber));
    }

    public function test_a_machine_whose_operator_has_no_grab_account_is_refused_and_nothing_changes(): void
    {
        $old = $this->bullaStoreOn(2852);
        $ref = $old->deliveryPlatformRefNumber;
        $this->service()->unbind($old, $this->user->id);
        $himd = Operator::forceCreate(['name' => 'HI MINDEF', 'code' => 'HIMD', 'country_id' => $this->lea->country_id, 'created_by' => 1, 'gst_vat_rate' => 9]);

        try {
            $this->service()->bind($ref->fresh(), $this->machine(2705, $himd->id), null, $this->user->id);
            $this->fail('bind should be refused');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('HIMD, which has no Grab account', $e->errors()['vend_code'][0]);
        }

        $this->assertSame($this->lea->id, (int) $ref->fresh()->operator_id);
        $this->assertSame(0, $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->count());
    }

    public function test_a_refused_bind_leaves_the_operator_as_it_was(): void
    {
        $old = $this->bullaStoreOn(2852);
        $ref = $old->deliveryPlatformRefNumber;
        $this->service()->unbind($old, $this->user->id);
        $busy = $this->bindGrabStore(2688, '4-C7CCRTEJJLJGNX', [11 => ['qty' => 5, 'capacity' => 10]]);

        $this->expectException(ValidationException::class);
        try {
            $this->service()->bind($ref->fresh(), $busy->vend, null, $this->user->id);
        } finally {
            $this->assertSame($this->lea->id, (int) $ref->fresh()->operator_id);
        }
    }
}
