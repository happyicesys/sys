<?php

namespace Tests\Unit;

use App\Services\Citybox\CityboxMoney;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CityboxMoneyTest extends TestCase
{
    public function test_decimal_strings_convert_to_integer_cents(): void
    {
        // Values straight from their order-push example.
        $this->assertSame(405, CityboxMoney::toCents('4.05'));
        $this->assertSame(450, CityboxMoney::toCents('4.50'));
        $this->assertSame(45, CityboxMoney::toCents('0.45'));
        $this->assertSame(0, CityboxMoney::toCents('0.00'));
        $this->assertSame(2042, CityboxMoney::toCents('20.42'));
    }

    public function test_single_decimal_whole_and_negative_amounts(): void
    {
        $this->assertSame(450, CityboxMoney::toCents('4.5'));
        $this->assertSame(400, CityboxMoney::toCents('4'));
        $this->assertSame(-349, CityboxMoney::toCents('-3.49'));
    }

    public function test_null_and_empty_pass_through_as_null(): void
    {
        $this->assertNull(CityboxMoney::toCents(null));
        $this->assertNull(CityboxMoney::toCents(''));
        $this->assertNull(CityboxMoney::toCents('  '));
    }

    public function test_more_than_two_decimals_is_rejected_not_rounded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CityboxMoney::toCents('4.055');
    }

    public function test_garbage_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CityboxMoney::toCents('4,05');
    }
}
