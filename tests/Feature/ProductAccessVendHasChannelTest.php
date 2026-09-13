<?php

namespace Tests\Feature;

use App\Support\ProductAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Operation Dashboard: a product-restricted viewer (prod_owner) must not see a
 * machine that carries none of their products on a live channel.
 */
class ProductAccessVendHasChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrestricted_viewer_adds_no_predicate(): void
    {
        $this->assertNull(ProductAccess::vendHasChannelSql('vends.id', null));
    }

    public function test_restricted_to_nothing_matches_nothing(): void
    {
        $this->assertSame('1 = 0', ProductAccess::vendHasChannelSql('vends.id', []));
    }

    public function test_keeps_only_vends_with_a_live_channel_of_an_allowed_product(): void
    {
        $channel = fn (int $vendId, int $code, ?int $productId, bool $active = true, int $capacity = 10) => [
            'vend_id' => $vendId,
            'code' => $code,
            'product_id' => $productId,
            'is_active' => $active,
            'capacity' => $capacity,
            'qty' => 0,
        ];

        DB::table('vend_channels')->insert([
            $channel(1, 11, 500),                    // own product      -> kept
            $channel(1, 12, 900),
            $channel(2, 11, 900),                    // others only      -> dropped
            $channel(2, 12, null),
            $channel(3, 11, 500, active: false),     // inactive channel -> dropped
            $channel(4, 11, 500, capacity: 0),       // zero capacity    -> dropped
        ]);

        $sql = ProductAccess::vendHasChannelSql('v.id', [500, 501]);

        $kept = DB::query()
            ->fromRaw('(SELECT 1 AS id UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) v')
            ->whereRaw($sql)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1], $kept);
    }
}
