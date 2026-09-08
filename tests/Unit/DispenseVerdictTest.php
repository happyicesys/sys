<?php

namespace Tests\Unit;

use App\Support\DispenseVerdict;
use App\Support\SaleStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * DispenseVerdict is the single definition of what a channel error code means.
 * Three questions, three answers — and the SQL builders must say exactly what
 * the PHP predicates say, or an aggregate and a screen will disagree.
 */
class DispenseVerdictTest extends TestCase
{
    public function test_the_three_code_sets(): void
    {
        $this->assertSame([0, 6], DispenseVerdict::DISPENSED_CODES);
        $this->assertSame(99, DispenseVerdict::NOT_FOUND_CODE);
        $this->assertSame([0, 6, 99], DispenseVerdict::SALE_CODES);
        $this->assertSame([99], DispenseVerdict::SERVER_RESERVED_CODES);

        // SaleStatus (display) keeps the machine-only set.
        $this->assertSame(DispenseVerdict::DISPENSED_CODES, SaleStatus::DISPENSED_CODES);
    }

    #[DataProvider('codes')]
    public function test_php_predicates(int|string|null $code, bool $sale, bool $dispensed, bool $fault): void
    {
        $this->assertSame($sale, DispenseVerdict::isSaleCode($code), "isSaleCode({$this->label($code)})");
        $this->assertSame($dispensed, DispenseVerdict::isDispensed($code), "isDispensed({$this->label($code)})");
        $this->assertSame($fault, DispenseVerdict::isMachineFault($code), "isMachineFault({$this->label($code)})");
    }

    public static function codes(): array
    {
        //            code    sale   dispensed fault
        return [
            'null' => [null, true, true, false],
            'empty' => ['', true, true, false],
            '0' => [0, true, true, false],
            '0 str' => ['0', true, true, false],
            '6' => [6, true, true, false],
            '6 str' => ['06', true, true, false],
            '99 NA' => [99, true, false, false],
            '99 str' => ['99', true, false, false],
            '7' => [7, false, false, true],
            '4' => [4, false, false, true],
            '9 str' => ['9', false, false, true],
            'junk' => ['abc', false, false, false], // unreadable: not a sale, not dispensed, not a fault
            'padded' => [' 7 ', false, false, true],
        ];
    }

    public function test_server_reserved(): void
    {
        $this->assertTrue(DispenseVerdict::isServerReserved(99));
        $this->assertTrue(DispenseVerdict::isServerReserved('99'));
        $this->assertFalse(DispenseVerdict::isServerReserved(0));
        $this->assertFalse(DispenseVerdict::isServerReserved(null));
    }

    public function test_sql_fragments_mirror_the_php_predicates(): void
    {
        $this->assertSame('0, 6, 99', DispenseVerdict::saleList());
        $this->assertSame('0, 4, 5, 6, 99', DispenseVerdict::faultList([4, 5]));

        $this->assertSame(
            '(vce.code IS NULL OR vce.code IN (0, 6, 99))',
            DispenseVerdict::sqlSale('vce.code')
        );
        $this->assertSame(
            '(vt.vend_channel_error_id IS NULL OR vce.code IN (0, 6, 99))',
            DispenseVerdict::sqlSaleById('vt.vend_channel_error_id', 'vce.code')
        );
        $this->assertSame(
            '(vce.code IS NOT NULL AND vce.code NOT IN (0, 6, 99))',
            DispenseVerdict::sqlFault('vce.code')
        );
        $this->assertSame(
            '(vce.code IS NOT NULL AND vce.code NOT IN (0, 4, 5, 6, 99))',
            DispenseVerdict::sqlFault('vce.code', [4, 5])
        );
        $this->assertSame(
            '(vt.vend_channel_error_id IS NOT NULL AND (vce.code IS NULL OR vce.code NOT IN (0, 6, 99)))',
            DispenseVerdict::sqlFaultById('vt.vend_channel_error_id', 'vce.code')
        );
        $this->assertSame(
            '(vt.vend_channel_error_id IS NOT NULL AND vce.code NOT IN (0, 6, 99))',
            DispenseVerdict::sqlFaultStrict('vt.vend_channel_error_id', 'vce.code')
        );
    }

    private function label(int|string|null $code): string
    {
        return var_export($code, true);
    }
}
