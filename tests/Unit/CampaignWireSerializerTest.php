<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Services\CampaignWireSerializer;
use Tests\TestCase;

/**
 * CampaignWireSerializer derives the legacy bundle-tier wire keys the whole
 * deployed fleet executes (enableBundleDiscount, bundleStartDate/EndDate,
 * enableDiscount01..03, discountPercent01..03) from bound QtyTier campaigns.
 *
 * Wire types are sacred: enables are the STRINGS 'true'/'false', percents are
 * real ints, dates are 'Y-m-d' strings or null — assertSame throughout.
 */
class CampaignWireSerializerTest extends TestCase
{
    private CampaignWireSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new CampaignWireSerializer;
    }

    /**
     * In-memory campaign — no DB. value passes through the model's ×100
     * mutator / ÷100 accessor exactly as a persisted row would.
     */
    private function campaign(int $id, string $promoType, ?int $qty = null, $percent = null, ?string $startAt = null, ?string $endAt = null): Campaign
    {
        $campaign = new Campaign;

        $campaign->forceFill([
            'id' => $id,
            'is_active' => true,
            'promo_type' => $promoType,
            'bundle_qty' => $qty,
            'value' => $percent,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);

        return $campaign;
    }

    private function tier(int $id, int $qty, $percent, ?string $startAt = null, ?string $endAt = null): Campaign
    {
        return $this->campaign($id, Campaign::TYPE_QTY_TIER, $qty, $percent, $startAt, $endAt);
    }

    /**
     * A hand-authored legacy blob, as a profile without QtyTier campaigns
     * would carry it.
     */
    private function handSetParams(): array
    {
        return [
            'someOtherKey' => 'untouched',
            'enableBundleDiscount' => 'true',
            'bundleStartDate' => '2025-01-01',
            'bundleEndDate' => '2025-12-31',
            'enableDiscount01' => 'true',
            'discountPercent01' => 7,
            'enableDiscount02' => 'false',
            'discountPercent02' => 1,
            'enableDiscount03' => 'false',
            'discountPercent03' => 1,
        ];
    }

    public function test_thresholds_map_to_slots_with_wire_safe_types(): void
    {
        $result = $this->serializer->applyQtyTierOverrides($this->handSetParams(), [
            $this->tier(1, 2, 10),
            $this->tier(2, 4, 25),
        ]);

        $this->assertSame('true', $result['enableBundleDiscount']);

        // bundle_qty 2 → slot 01, bundle_qty 4 → slot 03.
        $this->assertSame('true', $result['enableDiscount01']);
        $this->assertSame(10, $result['discountPercent01']);
        $this->assertSame('true', $result['enableDiscount03']);
        $this->assertSame(25, $result['discountPercent03']);

        // Unfilled slot 02 is explicitly disabled, not left at the hand-set
        // value.
        $this->assertSame('false', $result['enableDiscount02']);
        $this->assertSame(1, $result['discountPercent02']);

        // Non-tier keys ride along untouched.
        $this->assertSame('untouched', $result['someOtherKey']);
    }

    public function test_middle_threshold_fills_slot_02(): void
    {
        $result = $this->serializer->applyQtyTierOverrides($this->handSetParams(), [
            $this->tier(1, 3, 15),
        ]);

        $this->assertSame('true', $result['enableDiscount02']);
        $this->assertSame(15, $result['discountPercent02']);
        $this->assertSame('false', $result['enableDiscount01']);
        $this->assertSame(1, $result['discountPercent01']);
        $this->assertSame('false', $result['enableDiscount03']);
        $this->assertSame(1, $result['discountPercent03']);
    }

    public function test_window_is_min_start_and_max_end(): void
    {
        $result = $this->serializer->applyQtyTierOverrides($this->handSetParams(), [
            $this->tier(1, 2, 10, '2026-03-05 08:00:00', '2026-04-01 00:00:00'),
            $this->tier(2, 3, 15, '2026-02-01 00:00:00', '2026-06-30 23:59:59'),
        ]);

        $this->assertSame('2026-02-01', $result['bundleStartDate']);
        $this->assertSame('2026-06-30', $result['bundleEndDate']);
    }

    public function test_any_null_end_makes_the_window_end_unbounded(): void
    {
        $result = $this->serializer->applyQtyTierOverrides($this->handSetParams(), [
            $this->tier(1, 2, 10, '2026-02-01 00:00:00', '2026-06-30 00:00:00'),
            $this->tier(2, 3, 15, '2026-03-01 00:00:00', null),
        ]);

        $this->assertSame('2026-02-01', $result['bundleStartDate']);
        $this->assertNull($result['bundleEndDate']);
    }

    public function test_all_null_starts_make_the_window_start_unbounded(): void
    {
        $result = $this->serializer->applyQtyTierOverrides($this->handSetParams(), [
            $this->tier(1, 2, 10, null, '2026-06-30 00:00:00'),
            $this->tier(2, 3, 15, null, '2026-05-31 00:00:00'),
        ]);

        $this->assertNull($result['bundleStartDate']);
        $this->assertSame('2026-06-30', $result['bundleEndDate']);
    }

    /**
     * The device applies ONE window to the whole ladder and treats an empty
     * bound as unbounded — so an undated (always-active) tier must make the
     * union start unbounded, not inherit a dated sibling's start.
     */
    public function test_any_null_start_makes_the_window_start_unbounded(): void
    {
        $result = $this->serializer->applyQtyTierOverrides($this->handSetParams(), [
            $this->tier(1, 2, 10, '2026-09-01 00:00:00', '2026-12-31 00:00:00'),
            $this->tier(2, 3, 15, null, '2026-12-31 00:00:00'),
        ]);

        $this->assertNull($result['bundleStartDate']);
        $this->assertSame('2026-12-31', $result['bundleEndDate']);
    }

    public function test_duplicate_threshold_resolves_by_ascending_id_last_write_wins(): void
    {
        // Passed out of id order on purpose — the serializer sorts by id, so
        // the higher id (9) deterministically wins the slot.
        $result = $this->serializer->applyQtyTierOverrides($this->handSetParams(), [
            $this->tier(9, 3, 20),
            $this->tier(5, 3, 15),
        ]);

        $this->assertSame('true', $result['enableDiscount02']);
        $this->assertSame(20, $result['discountPercent02']);
    }

    /**
     * THE backward-compatibility contract: no QtyTier campaign bound → the
     * blob is returned byte-identical, hand-set legacy tier values included.
     */
    public function test_untouched_when_no_qty_tier_campaign_is_bound(): void
    {
        $params = $this->handSetParams();

        $this->assertSame($params, $this->serializer->applyQtyTierOverrides($params, []));

        // Other campaign types in the collection change nothing either.
        $this->assertSame($params, $this->serializer->applyQtyTierOverrides($params, [
            $this->campaign(1, Campaign::TYPE_PERCENTAGE, null, 10),
            $this->campaign(2, Campaign::TYPE_ITEM, 2, null),
        ]));
    }

    public function test_exclude_qty_tier_strips_only_qty_tier_campaigns(): void
    {
        $percentage = $this->campaign(1, Campaign::TYPE_PERCENTAGE, null, 10);
        $tier = $this->tier(2, 3, 15);

        $remaining = $this->serializer->excludeQtyTier([$percentage, $tier]);

        $this->assertCount(1, $remaining);
        $this->assertSame($percentage, $remaining->first());
    }
}
