<?php

namespace Tests\Unit;

use App\Http\Resources\VendResource;
use App\Models\OpsJobItem;
use App\Models\ProductMapping;
use App\Models\Vend;
use Tests\TestCase;

/**
 * The "Implement New Mapping" description on OpsJob/Edit.vue must always be the
 * UPCOMING (to-be) mapping's remarks — never the mapping the machine is on today.
 *
 * The case this locks down: the vend has NO own upcoming
 * (vends.upcoming_product_mapping_id IS NULL) but the mapping it is bound to
 * carries a preset upcoming (product_mappings.upcoming_product_mapping_id).
 * The description must then come from that preset upcoming mapping.
 *
 * Two paths render that box and both are covered here:
 *   - live rows  -> Edit.vue rowMappingRemarks() reads the Inertia payload
 *                   (vend.upcomingProductMapping ?: vend.productMapping.upcomingProductMapping)
 *   - frozen rows -> OpsJobItem::resolveMappingSnapshot() ['mapping_remarks']
 *
 * No database required.
 *
 * Run: php artisan test --filter=OpsJobItemMappingRemarksTest
 */
class OpsJobItemMappingRemarksTest extends TestCase
{
    private const CURRENT_REMARKS = 'CURRENT: #11 Rocket 换去 Blind stick';

    private const UPCOMING_REMARKS = 'UPCOMING: #13 Milo 换去 Jelly';

    private function mapping(int $id, string $name, string $remarks): ProductMapping
    {
        $mapping = new ProductMapping;
        $mapping->id = $id;
        $mapping->name = $name;
        $mapping->remarks = $remarks;

        return $mapping;
    }

    /**
     * A vend on a current mapping that presets an upcoming one, with NO upcoming
     * of its own — the shape this test exists for.
     */
    private function vendWithPresetUpcomingOnly(?string $startDate = null): Vend
    {
        $upcoming = $this->mapping(621, 'TO_BE', self::UPCOMING_REMARKS);

        $current = $this->mapping(588, 'CURRENT', self::CURRENT_REMARKS);
        $current->upcoming_product_mapping_id = $upcoming->id;
        $current->upcoming_product_mapping_start_date = $startDate;
        $current->setRelation('upcomingProductMapping', $upcoming);

        $vend = new Vend;
        $vend->id = 1215;
        $vend->upcoming_product_mapping_id = null;
        $vend->setRelation('productMapping', $current);
        // Loaded but null — this is what the eager load produces for a vend with
        // no own upcoming, and it must serialise to a FALSY value so the
        // frontend's `a || b` falls through to the current mapping's preset.
        $vend->setRelation('upcomingProductMapping', null);

        return $vend;
    }

    private function item(Vend $vend): OpsJobItem
    {
        $item = new OpsJobItem;
        $item->stock_action_type = 'implement_new_mapping';
        $item->setRelation('vend', $vend);

        return $item;
    }

    // ── Live rows: the Inertia payload Edit.vue reads ──────────────────────────

    public function test_payload_exposes_the_preset_upcoming_remarks_not_the_current_ones(): void
    {
        $payload = json_decode(VendResource::make($this->vendWithPresetUpcomingOnly())->toJson(), true);

        $this->assertSame(
            self::UPCOMING_REMARKS,
            $payload['productMapping']['upcomingProductMapping']['remarks'],
            'The to-be mapping\'s remarks must reach the frontend.'
        );
        $this->assertSame(self::CURRENT_REMARKS, $payload['productMapping']['remarks']);
    }

    public function test_vend_own_upcoming_is_falsy_when_loaded_but_null(): void
    {
        // Edit.vue resolves `vend.upcomingProductMapping || vend.productMapping.upcomingProductMapping`.
        // If a loaded-but-null relation serialised as an object shell ({id: null,
        // remarks: null, ...}) that OR would stop on it and the description would
        // silently vanish. Laravel nulls it out — assert that, it is the contract.
        $payload = json_decode(VendResource::make($this->vendWithPresetUpcomingOnly())->toJson(), true);

        $this->assertNull($payload['upcomingProductMapping'] ?? null);
    }

    public function test_vend_own_upcoming_wins_over_the_preset_when_set(): void
    {
        $vend = $this->vendWithPresetUpcomingOnly();
        $own = $this->mapping(700, 'VEND_OWN_TO_BE', 'OWN: #16 Pistachio 换去 Magnum');
        $vend->upcoming_product_mapping_id = $own->id;
        $vend->setRelation('upcomingProductMapping', $own);

        $payload = json_decode(VendResource::make($vend)->toJson(), true);

        $this->assertSame('OWN: #16 Pistachio 换去 Magnum', $payload['upcomingProductMapping']['remarks']);
    }

    // ── Frozen rows: the snapshot taken 10 min after stock-in ─────────────────

    public function test_snapshot_freezes_the_preset_upcoming_remarks(): void
    {
        $snapshot = $this->item($this->vendWithPresetUpcomingOnly())->resolveMappingSnapshot();

        $this->assertSame(self::UPCOMING_REMARKS, $snapshot['mapping_remarks']);
        $this->assertSame('CURRENT', $snapshot['mapping_current_name']);
        $this->assertSame('TO_BE', $snapshot['mapping_upcoming_via_current']);
        $this->assertNull($snapshot['mapping_upcoming_direct']);
    }

    public function test_snapshot_prefers_the_vends_own_upcoming_remarks(): void
    {
        $vend = $this->vendWithPresetUpcomingOnly();
        $own = $this->mapping(700, 'VEND_OWN_TO_BE', 'OWN REMARKS');
        $vend->upcoming_product_mapping_id = $own->id;
        $vend->setRelation('upcomingProductMapping', $own);

        $snapshot = $this->item($vend)->resolveMappingSnapshot();

        $this->assertSame('OWN REMARKS', $snapshot['mapping_remarks']);
        $this->assertSame('VEND_OWN_TO_BE', $snapshot['mapping_upcoming_direct']);
    }

    public function test_snapshot_holds_no_remarks_before_the_declared_start_date(): void
    {
        $snapshot = $this->item(
            $this->vendWithPresetUpcomingOnly(now()->addWeek()->toDateString())
        )->resolveMappingSnapshot();

        $this->assertNull($snapshot['mapping_remarks']);
        $this->assertNull($snapshot['mapping_upcoming_via_current']);
    }

    public function test_snapshot_holds_the_remarks_once_the_start_date_is_reached(): void
    {
        $snapshot = $this->item(
            $this->vendWithPresetUpcomingOnly(now()->subDay()->toDateString())
        )->resolveMappingSnapshot();

        $this->assertSame(self::UPCOMING_REMARKS, $snapshot['mapping_remarks']);
    }
}
