<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SmartFreezerOwnAiCheck;
use App\Models\SmartFreezerOwnAiRecognition;
use App\Models\SmartFreezerRecognition;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * AI Recognition → HappyIce AI → "Check HappyIce AI" (Brian, 2026-11-22: "a button at Happy ice AI ... mark it as accurate
 * or ... tell the answer straight over there, so every 3 AM you can train based on the truth"). Shadow only.
 */
class OwnAiCheckTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    private Product $musang;

    private Product $grape;

    private SmartFreezerRecognition $r;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('read ai-recognition', 'web');
        Permission::findOrCreate('update ai-recognition', 'web');
        config(['smart_freezer.own_ai.enabled' => true, 'smart_freezer.own_ai.worker_priority' => ['mini', 'm1']]);
        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->musang = Product::forceCreate(['code' => 'OK-01', 'name' => 'Musang King Cup', 'operator_id' => 1]);
        $this->grape = Product::forceCreate(['code' => 'U-86', 'name' => 'Walls Ice Ball Red Grape', 'operator_id' => 1]);
        $other = Product::forceCreate(['code' => 'U-01', 'name' => 'Other Pack', 'operator_id' => 1]);
        VendChannel::forceCreate(['vend_id' => $this->vend->id, 'code' => 61, 'product_id' => $other->id, 'is_active' => true]);

        // #242 (2026-11-22 15:27): paid 2 Musang King, Zijia saw 2, HappyIce AI said 2 + 1 Red Grape.
        $ref = 'SF-50001-1795000000-7';
        $this->r = SmartFreezerRecognition::create(['vend_id' => $this->vend->id, 'trade_id' => 'SDK'.uniqid(), 'session_ref' => $ref,
            'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'verdict' => 'match',
            'verdict_lines' => [['product_id' => $this->musang->id, 'paid' => 2, 'taken' => 2, 'delta' => 0]]]);
        VendTransaction::forceCreate([
            'order_id' => 'O-'.uniqid(), 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 900,
            'transaction_datetime' => now(), 'gst_vat_rate' => 9, 'operator_id' => 1, 'is_multiple' => true,
            'vend_transaction_json' => ['SFREF' => $ref, 'transf_info' => [['goods_id' => $this->musang->id], ['goods_id' => $this->musang->id]]],
        ]);
        SmartFreezerOwnAiRecognition::create(['smart_freezer_recognition_id' => $this->r->id, 'worker' => 'm1', 'vend_id' => $this->vend->id,
            'items' => [(string) $this->musang->id => 2, (string) $this->grape->id => 1], 'confidence' => 53, 'auto_accept' => false,
            'why_person' => 'disagrees with the receipt', 'model' => 'v6-live-2026-11-22', 'received_at' => now()]);
    }

    private function checker(): User
    {
        $user = User::factory()->create(['operator_id' => 1, 'name' => 'Brian']);
        $user->givePermissionTo(['read ai-recognition', 'update ai-recognition']);

        return $user;
    }

    public function test_the_form_lists_paid_zijia_and_happyice_and_starts_from_happyice(): void
    {
        $lines = collect($this->actingAs($this->checker())->getJson("/ai-recognition/{$this->r->id}/happyice-check")
            ->assertOk()->json('editor.lines'))->keyBy('product_id');

        $this->assertSame(['paid' => 2, 'zijia' => 2, 'happyice' => 2, 'taken' => 2],
            array_intersect_key($lines[$this->musang->id], array_flip(['paid', 'zijia', 'happyice', 'taken'])));
        $this->assertSame(['paid' => 0, 'zijia' => 0, 'happyice' => 1, 'taken' => 1],
            array_intersect_key($lines[$this->grape->id], array_flip(['paid', 'zijia', 'happyice', 'taken'])));
        $this->assertCount(3, $lines, 'the machine\'s other products can be added');
    }

    public function test_wrong_saves_the_truth_and_the_note_and_moves_nothing(): void
    {
        $sale = VendTransaction::first();
        $before = $sale->getAttributes();

        $this->actingAs($this->checker())->postJson("/ai-recognition/{$this->r->id}/happyice-check", [
            'outcome' => 'wrong', 'taken' => [$this->musang->id => 2, $this->grape->id => 0], 'note' => 'counted a grape put back',
        ])->assertOk()->assertJson(['ok' => true]);

        $check = SmartFreezerOwnAiCheck::sole();
        $this->assertSame('wrong', $check->outcome);
        $this->assertEquals([$this->musang->id => 2], $check->items);
        $this->assertEquals([$this->musang->id => 2, $this->grape->id => 1], $check->ai_items);
        $this->assertSame('counted a grape put back', $check->note);
        $this->assertSame('m1', $check->ai_worker);
        $this->assertEquals($before, $sale->fresh()->getAttributes(), 'no sale, money or stock moves');

        // The page shows it under HappyIce AI's answer, and HappyIce AI's units are judged against it.
        $this->actingAs($this->checker())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canCheckOwnAi', true)
                ->where('recognitions.data.0.own_ai.check.outcome', 'wrong')
                ->where('recognitions.data.0.own_ai.check.by', 'Brian')
                ->where('recognitions.data.0.own_ai.check.note', 'counted a grape put back')
                ->where('recognitions.data.0.own_ai.check.items.0.name', 'Musang King Cup')
                ->where('recognitions.data.0.own_ai.check.items.0.number', 2)
                ->where('dashboard.happyice.ours_correct_qty', 2)
                ->where('dashboard.happyice.ours_wrong_qty', 1)
                ->where('dashboard.happyice.ours_unchecked_qty', 0));
    }

    public function test_right_keeps_happyice_count_and_a_second_check_replaces_the_first(): void
    {
        $user = $this->checker();
        $this->actingAs($user)->postJson("/ai-recognition/{$this->r->id}/happyice-check", ['outcome' => 'wrong', 'taken' => [$this->musang->id => 1]])->assertOk();
        $this->actingAs($user)->postJson("/ai-recognition/{$this->r->id}/happyice-check", ['outcome' => 'right'])->assertOk();

        $check = SmartFreezerOwnAiCheck::sole();
        $this->assertSame('right', $check->outcome);
        $this->assertEquals([$this->musang->id => 2, $this->grape->id => 1], $check->items);
    }

    public function test_cant_tell_keeps_no_truth_and_is_left_out_of_the_score(): void
    {
        $this->actingAs($this->checker())->postJson("/ai-recognition/{$this->r->id}/happyice-check", [
            'outcome' => 'unclear', 'note' => 'camera 3 stopped before the lid opened',
        ])->assertOk();

        $check = SmartFreezerOwnAiCheck::sole();
        $this->assertSame('unclear', $check->outcome);
        $this->assertEquals([], (array) $check->items);
        $this->assertEquals([$this->musang->id => 2, $this->grape->id => 1], $check->ai_items);

        // The form starts from HappyIce AI's count again, and the score box judges by the paid order as if unchecked.
        $lines = collect($this->getJson("/ai-recognition/{$this->r->id}/happyice-check")->json('editor.lines'))->keyBy('product_id');
        $this->assertSame(1, $lines[$this->grape->id]['taken']);
        $this->actingAs($this->checker())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.own_ai.check.outcome', 'unclear')
                ->where('dashboard.happyice.ours_wrong_qty', 0)
                ->where('dashboard.happyice.ours_unchecked_qty', 1));
    }

    public function test_wrong_with_happyice_own_count_is_refused(): void
    {
        $this->actingAs($this->checker())->postJson("/ai-recognition/{$this->r->id}/happyice-check", [
            'outcome' => 'wrong', 'taken' => [$this->musang->id => 2, $this->grape->id => 1],
        ])->assertStatus(422)->assertJsonPath('editor.recognition_id', $this->r->id);
        $this->assertSame(0, SmartFreezerOwnAiCheck::count());
    }

    public function test_it_needs_update_ai_recognition_and_an_answer(): void
    {
        $reader = User::factory()->create(['operator_id' => 1]);
        $reader->givePermissionTo('read ai-recognition');
        $this->actingAs($reader)->postJson("/ai-recognition/{$this->r->id}/happyice-check", ['outcome' => 'right'])->assertForbidden();
        $this->actingAs($reader)->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canCheckOwnAi', false));

        SmartFreezerOwnAiRecognition::query()->delete();
        $this->actingAs($this->checker())->postJson("/ai-recognition/{$this->r->id}/happyice-check", ['outcome' => 'right'])->assertStatus(422);
    }

    public function test_another_operators_session_is_not_found(): void
    {
        $outsider = User::factory()->create(['operator_id' => 2]);
        $outsider->givePermissionTo(['read ai-recognition', 'update ai-recognition']);
        \App\Models\Operator::forceCreate(['id' => 2, 'name' => 'Other', 'code' => 'OTH']);

        $this->actingAs($outsider)->getJson("/ai-recognition/{$this->r->id}/happyice-check")->assertNotFound();
    }
}
