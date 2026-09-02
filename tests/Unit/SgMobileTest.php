<?php

namespace Tests\Unit;

use App\Support\SgMobile;
use PHPUnit\Framework\TestCase;

/**
 * The PayNow mobile rule is ours, not libphonenumber's: 8 digits starting
 * with 8 or 9, after stripping spaces, dashes and an optional +65 / 65 prefix.
 */
class SgMobileTest extends TestCase
{
    public function test_accepts_eight_digit_numbers_starting_with_8_or_9()
    {
        foreach (['89844833', '80000000', '99999999', '91234567'] as $n) {
            $this->assertTrue(SgMobile::isValid($n), $n);
            $this->assertSame($n, SgMobile::normalise($n));
            $this->assertSame('+65'.$n, SgMobile::e164($n));
        }
    }

    public function test_strips_country_prefix_spaces_and_dashes()
    {
        foreach (['+65 8984 4833', '+6589844833', '6589844833', '006589844833', '65 8984-4833', '(65) 8984.4833', ' 8984 4833 '] as $n) {
            $this->assertSame('89844833', SgMobile::normalise($n), $n);
        }
    }

    public function test_rejects_everything_else()
    {
        $bad = [
            '61234567',    // landline (starts with 6)
            '31234567',    // VoIP range (starts with 3)
            '8984483',     // 7 digits
            '898448331',   // 9 digits
            '+6598844833x',
            '9884483a',
            'not-a-phone',
            '',
            null,
            '+44 7911 123456', // UK mobile
        ];
        foreach ($bad as $n) {
            $this->assertFalse(SgMobile::isValid($n), var_export($n, true));
            $this->assertNull(SgMobile::e164($n), var_export($n, true));
        }
    }
}
