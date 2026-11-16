<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\Vend;
use App\Services\SmartFreezerCatalogPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A planogram edit nudges the freezers on that mapping to re-read /menu (2026-11-16). The push used to
 * select machines by model "Smart Vend", which no freezer has (all are "SCF 338"), so a mapping edit —
 * and the menu NEW badge it creates — reached a freezer only at its next reconnect (found on bench 2009).
 */
class SmartFreezerCatalogPushTest extends TestCase
{
    use RefreshDatabase;

    private function freezer(int $code, ProductMapping $mapping, array $attrs = []): Vend
    {
        $modelId = DB::table('vend_models')->insertGetId(['name' => 'SCF 338', 'created_at' => now(), 'updated_at' => now()]);
        $vend = new Vend;
        $vend->forceFill(array_merge([
            'code' => $code, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1,
            'operator_id' => 1, 'vend_model_id' => $modelId, 'product_mapping_id' => $mapping->id,
            'private_key' => 'TESTKEY000000001',
        ], $attrs))->save();

        return $vend;
    }

    /** @return array<int, string> topics of every published frame */
    private function topics(): array
    {
        $topics = [];
        Queue::assertPushed(PublishMqtt::class, function (PublishMqtt $job) use (&$topics) {
            $topics[] = (fn () => $this->topic)->call($job);

            return true;
        });

        return $topics;
    }

    public function test_a_mapping_reaches_its_active_freezers_whatever_their_model(): void
    {
        Queue::fake();
        $mapping = ProductMapping::forceCreate(['name' => 'SF1', 'is_smart' => true, 'machine_type' => 'smart_freezer']);
        $this->freezer(2009, $mapping);
        $this->freezer(2013, $mapping, ['is_active' => 0]);

        $result = app(SmartFreezerCatalogPush::class)->pushForMapping($mapping);

        $this->assertSame(['targets' => 1, 'pushed' => 1], array_intersect_key($result, ['targets' => 1, 'pushed' => 1]));
        $this->assertSame(['CM2009'], $this->topics());
    }

    public function test_a_vending_machine_on_the_mapping_is_not_sent_the_freezer_nudge(): void
    {
        Queue::fake();
        $mapping = ProductMapping::forceCreate(['name' => 'Mixed']);
        $vend = new Vend;
        $vend->forceFill(['code' => 2031, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1,
            'operator_id' => 1, 'product_mapping_id' => $mapping->id])->save();

        $result = app(SmartFreezerCatalogPush::class)->pushForMapping($mapping);

        $this->assertSame(0, $result['targets']);
        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_a_freezer_slot_edit_through_the_controller_nudges_the_freezer(): void
    {
        Queue::fake();
        $mapping = ProductMapping::forceCreate(['name' => 'SF1', 'is_smart' => true, 'machine_type' => 'smart_freezer']);
        $this->freezer(2009, $mapping);
        $product = Product::create(['code' => 'G104', 'name' => 'Melona Melon']);

        app(\App\Http\Controllers\ProductMappingController::class)->createItem(
            \Illuminate\Http\Request::create('/x', 'POST', ['channel_code' => '33', 'product_id' => $product->id]),
            $mapping->id
        );

        $this->assertContains('CM2009', $this->topics());
    }
}
