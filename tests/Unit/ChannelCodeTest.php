<?php

namespace Tests\Unit;

use App\Support\ChannelCode;
use PHPUnit\Framework\TestCase;

/**
 * "101A" is one position split between SKUs (Brian, 2026-09-22): the number is
 * the shelf, the letter the part. Run: php artisan test --filter=ChannelCodeTest
 */
class ChannelCodeTest extends TestCase
{
    public function test_parses_plain_and_lettered_codes_and_rejects_the_rest(): void
    {
        $this->assertSame(['code' => 17, 'suffix' => null], ChannelCode::parse('17'));
        $this->assertSame(['code' => 17, 'suffix' => null], ChannelCode::parse(17));
        $this->assertSame(['code' => 101, 'suffix' => 'A'], ChannelCode::parse('101A'));
        $this->assertSame(['code' => 101, 'suffix' => 'B'], ChannelCode::parse(' 101b '));
        $this->assertNull(ChannelCode::parse('101AB'));
        $this->assertNull(ChannelCode::parse('A101'));
        $this->assertNull(ChannelCode::parse('-17'));
        $this->assertNull(ChannelCode::parse(''));
        $this->assertNull(ChannelCode::parse(null));
    }

    public function test_label_and_normalize_round_trip(): void
    {
        $this->assertSame('101A', ChannelCode::label(101, 'a'));
        $this->assertSame('101', ChannelCode::label(101, null));
        $this->assertSame('101A', ChannelCode::normalize(' 101a'));
        $this->assertSame('17', ChannelCode::normalize('017'), 'a padded board code is canonicalised to its number');
        $this->assertSame('junk', ChannelCode::normalize('junk'), 'unparseable input is trimmed, never mangled');
        $this->assertTrue(ChannelCode::hasSuffix('101A'));
        $this->assertFalse(ChannelCode::hasSuffix('101'));
    }

    public function test_orders_by_number_then_letter(): void
    {
        $codes = ['102', '101B', '9', '101', '10', '101A', 'x1'];
        usort($codes, [ChannelCode::class, 'compare']);

        $this->assertSame(['9', '10', '101', '101A', '101B', '102', 'x1'], $codes);
    }
}
