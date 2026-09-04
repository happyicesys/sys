<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProductMapping;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendSticker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Per-field "who last changed this, and when" on Machine Settings
 * (VendController::fieldAudit, rendered by Components/FieldAudit.vue).
 *
 * Nothing is stored for it — every entry is derived from the app-wide
 * user_logs audit, so the rules that matter are: a real edit stamps exactly
 * the fields it touched, an unchanged re-save stamps nothing (the form posts
 * booleans/strings against int columns, so most "changes" are type-only), and
 * the Machine Stickers pivot — invisible to Eloquent events — is stamped too.
 */
class VendFieldAuditTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        foreach (['read machine-settings', 'update machine-settings'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $user = User::factory()->create(['name' => 'WenBin']);
        $user->givePermissionTo(['read machine-settings', 'update machine-settings']);

        return $user;
    }

    private function makeVend(int $code): Vend
    {
        $customer = Customer::create([
            'name' => "Site {$code}", 'code' => $code, 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE,
        ]);
        $mapping = ProductMapping::forceCreate(['name' => "Mapping {$code}"]);

        return Vend::forceCreate([
            'code' => $code, 'customer_id' => $customer->id, 'product_mapping_id' => $mapping->id,
            'operator_id' => 1, 'label_name' => 'Before', 'lcd_monitor_id' => 1,
            'menu_frame_id' => 1, 'vend_vend_config_version' => 'd', 'is_fan_enabled' => true,
        ]);
    }

    /** The full Setting/Edit payload, so a save exercises the real controller path. */
    private function payload(Vend $vend, array $overrides = []): array
    {
        return $overrides + [
            'label_name' => $vend->label_name,
            'lcd_monitor_id' => $vend->lcd_monitor_id,
            'menu_frame_id' => 1,
            'operator_id' => $vend->operator_id,
            'product_mapping_id' => $vend->product_mapping_id,
            'vend_model_id' => 1,
            'vend_prefix_id' => 1,
            'vend_vend_config_version' => $vend->vend_vend_config_version,
            'is_fan_enabled' => true,
            'status' => 'active',
        ];
    }

    public function test_an_edit_stamps_only_the_fields_it_changed(): void
    {
        $user = $this->editor();
        $vend = $this->makeVend(9701);

        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->payload($vend, [
                'label_name' => 'After',
                'vend_vend_config_version' => 'e',
            ]))
            ->assertSessionHasNoErrors();

        $audit = $this->actingAs($user)->getJson('/vends/'.$vend->id.'/field-audit')->assertOk()->json();

        $this->assertSame('WenBin', $audit['label_name']['who'] ?? null);
        $this->assertNotNull($audit['label_name']['at'] ?? null);

        // The field this whole change was asked for: the Setting Chart version.
        $this->assertSame('WenBin', $audit['vend_vend_config_version']['who'] ?? null);

        // Untouched fields must stay unstamped.
        $this->assertArrayNotHasKey('lcd_monitor_id', $audit);
        $this->assertArrayNotHasKey('menu_frame_id', $audit);
    }

    public function test_an_unchanged_resave_stamps_nothing(): void
    {
        $user = $this->editor();
        $vend = $this->makeVend(9702);

        // Two identical saves: the second changes nothing a human would call a change.
        $this->actingAs($user)->post('/vends/'.$vend->id.'/update', $this->payload($vend))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/vends/'.$vend->id.'/update', $this->payload($vend))
            ->assertSessionHasNoErrors();

        $audit = $this->actingAs($user)->getJson('/vends/'.$vend->id.'/field-audit')->assertOk()->json();

        // is_fan_enabled / status round-trip as bool-vs-int and would otherwise
        // stamp on every save; the endpoint's type-only filter must drop them.
        $this->assertArrayNotHasKey('is_fan_enabled', $audit);
        $this->assertArrayNotHasKey('status', $audit);
        $this->assertArrayNotHasKey('label_name', $audit);
    }

    public function test_a_sticker_change_is_stamped_even_though_the_pivot_fires_no_model_event(): void
    {
        $user = $this->editor();
        $vend = $this->makeVend(9703);
        $sticker = VendSticker::create(['name' => 'Unilever wrap']);

        // Attach.
        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->payload($vend, ['sticker_ids' => [$sticker->id]]))
            ->assertSessionHasNoErrors();

        $audit = $this->actingAs($user)->getJson('/vends/'.$vend->id.'/field-audit')->assertOk()->json();
        $this->assertSame('WenBin', $audit['sticker_ids']['who'] ?? null);
        $this->assertSame([$sticker->id], $vend->fresh()->stickers->pluck('id')->all());

        // Re-sending the same sticker is not a change and must not re-stamp.
        $stampedAt = DB::table('user_logs')->latest('id')->value('id');
        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->payload($vend, ['sticker_ids' => [$sticker->id]]))
            ->assertSessionHasNoErrors();
        $this->assertSame(
            0,
            DB::table('user_logs')->where('id', '>', $stampedAt)
                ->where('changes', 'like', '%sticker_ids%')->count(),
            'an unchanged sticker selection must not write an audit row'
        );

        // Clearing it is a change again ('' from the "--- Clear ---" option).
        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->payload($vend, ['sticker_ids' => []]))
            ->assertSessionHasNoErrors();
        $this->assertSame([], $vend->fresh()->stickers->pluck('id')->all());
        $this->assertSame(
            1,
            DB::table('user_logs')->where('id', '>', $stampedAt)
                ->where('changes', 'like', '%sticker_ids%')->count()
        );
    }

    public function test_the_endpoint_is_gated_on_read_machine_settings(): void
    {
        $vend = $this->makeVend(9704);
        Permission::findOrCreate('read machine-settings', 'web');

        $this->actingAs(User::factory()->create())
            ->getJson('/vends/'.$vend->id.'/field-audit')
            ->assertForbidden();
    }
}
