<?php

namespace Tests\Unit;

use App\Models\VendChannel;
use App\Services\StockCheck\StockCheckSampler;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Which channels a stock count asks the driver to count. Pure rules, no DB:
 * the sampler is handed channels and a fixed picker.
 */
class StockCheckSamplerTest extends TestCase
{
    private function channel(int $code, int $qty, int $capacity = 10, ?int $productId = 1, bool $active = true): VendChannel
    {
        $channel = new VendChannel;
        $channel->forceFill([
            'code' => $code, 'qty' => $qty, 'capacity' => $capacity,
            'product_id' => $productId, 'is_active' => $active,
        ]);

        return $channel;
    }

    /** @return Collection<int, VendChannel> */
    private function machine(): Collection
    {
        return collect([
            $this->channel(13, 4, productId: 2),
            $this->channel(11, 8, productId: 1),
            $this->channel(12, 0, productId: 1),               // sold out
            $this->channel(14, 5, capacity: 0, productId: 1),  // no capacity
            $this->channel(15, 5, productId: null),            // nothing mapped
            $this->channel(16, 5, productId: 3, active: false), // retired
            $this->channel(17, 1, productId: 3),
        ]);
    }

    public function test_a_sold_out_channel_is_never_offered_for_counting(): void
    {
        $codes = (new StockCheckSampler)->eligible($this->machine())->pluck('code')->all();

        // A spot check cannot validate "sold out"; it needs a channel the system
        // believes is stocked — including one showing a single piece left.
        $this->assertSame([11, 13, 17], $codes);
    }

    public function test_a_product_filter_narrows_the_pool(): void
    {
        $codes = (new StockCheckSampler)->eligible($this->machine(), [3])->pluck('code')->all();

        $this->assertSame([17], $codes);
    }

    public function test_not_random_takes_every_eligible_channel(): void
    {
        $sampler = new StockCheckSampler;
        $drawn = $sampler->draw($sampler->eligible($this->machine()), false, null);

        $this->assertSame([11, 13, 17], $drawn->pluck('code')->all());
    }

    public function test_random_draws_exactly_the_asked_number_in_channel_order(): void
    {
        // Fixed picker: always the LAST keys, so the assertion is deterministic.
        $sampler = new StockCheckSampler(fn (array $keys, int $count) => array_slice($keys, -$count));
        $drawn = $sampler->draw($sampler->eligible($this->machine()), true, 2);

        $this->assertSame([13, 17], $drawn->pluck('code')->all());
    }

    public function test_an_eloquent_collection_is_drawn_by_position_not_by_model_id(): void
    {
        // Regression: Eloquent\Collection::only() filters by primary key, so a
        // random draw over real query results came back EMPTY.
        $models = $this->machine()->values()->each(fn (VendChannel $c, int $i) => $c->forceFill(['id' => 9000 + $i]));
        $sampler = new StockCheckSampler(fn (array $keys, int $count) => array_slice($keys, 0, $count));

        $drawn = $sampler->draw($sampler->eligible(new \Illuminate\Database\Eloquent\Collection($models->all())), true, 2);

        $this->assertSame([11, 13], $drawn->pluck('code')->all());
    }

    public function test_asking_for_more_than_exist_takes_them_all(): void
    {
        $sampler = new StockCheckSampler(fn () => $this->fail('nothing to pick when the sample covers the pool'));
        $drawn = $sampler->draw($sampler->eligible($this->machine()), true, 99);

        $this->assertCount(3, $drawn);
    }

    public function test_the_default_picker_is_random_but_never_repeats_a_channel(): void
    {
        $sampler = new StockCheckSampler;
        $eligible = $sampler->eligible($this->machine());

        for ($i = 0; $i < 25; $i++) {
            $codes = $sampler->draw($eligible, true, 2)->pluck('code');
            $this->assertCount(2, $codes->unique());
            $this->assertEmpty($codes->diff([11, 13, 17]));
        }
    }
}
