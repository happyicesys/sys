<?php

namespace Tests\Unit;

use App\Support\VendCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VendCodeTest extends TestCase
{
    public static function externalNames(): array
    {
        return [
            'plain' => ['C6003', 'C', 6003],
            'with free text' => ['C6004 HI Office', 'C', 6004],
            'full-width colon' => ['C5001：29F 电梯旁', 'C', 5001],
            'lower case' => ['c5002', 'C', 5002],
            'hyphen' => ['CB-12', 'CB', 12],
            'leading space' => ['  C6001', 'C', 6001],
        ];
    }

    #[DataProvider('externalNames')]
    public function test_reads_the_machine_id_from_an_ops_pro_name(string $name, string $prefix, int $number): void
    {
        $code = VendCode::fromExternalName($name);

        $this->assertNotNull($code);
        $this->assertSame($prefix, $code->prefix);
        $this->assertSame($number, $code->number);
        $this->assertSame($prefix.$number, $code->toLabel());
    }

    public static function namesWithoutId(): array
    {
        return [
            'old placeholder' => ['Singapore8'],
            'hash' => ['#1'],
            'bare number' => ['6003'],
            'letters only' => ['HAPPYICE'],
            'zero' => ['C0'],
            'empty' => [''],
        ];
    }

    #[DataProvider('namesWithoutId')]
    public function test_a_name_without_a_machine_id_is_never_turned_into_one(string $name): void
    {
        $this->assertNull(VendCode::fromExternalName($name));
    }

    public function test_label_of_an_unprefixed_vend_is_just_its_code(): void
    {
        $this->assertSame('2031', VendCode::label(null, 2031));
        $this->assertSame('C6003', VendCode::label('C', 6003));
    }
}
